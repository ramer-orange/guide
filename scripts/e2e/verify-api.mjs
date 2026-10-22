import { readFile } from "node:fs/promises";

const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const fixturePath = process.env.E2E_FIXTURE_PATH ?? "storage/app/e2e-fixture.json";
const fixture = JSON.parse(await readFile(fixturePath, "utf8"));
const origin = new URL(baseURL).origin;
const forwardedHost = new URL(baseURL).host;

function cookieHeader(cookies) {
  return cookies.map(({ name, value }) => `${name}=${value}`).join("; ");
}

function mergeSetCookies(cookies, response) {
  const lines = response.headers.getSetCookie?.() ?? [];
  for (const line of lines) {
    const pair = line.split(";", 1)[0];
    const separator = pair.indexOf("=");
    if (separator > 0) {
      const name = pair.slice(0, separator);
      const value = pair.slice(separator + 1);
      const existing = cookies.findIndex((cookie) => cookie.name === name);
      const next = { name, value };
      if (existing < 0) cookies.push(next);
      else cookies[existing] = next;
    }
  }
}

async function request(path, { cookies = [], method = "GET", headers = {}, body } = {}) {
  const response = await fetch(new URL(path, `${baseURL}/`), {
    method,
    headers: {
      Host: forwardedHost,
      Origin: origin,
      Referer: `${origin}/`,
      "X-Forwarded-Host": forwardedHost,
      "X-Forwarded-Proto": new URL(baseURL).protocol.slice(0, -1),
      ...(cookies.length ? { Cookie: cookieHeader(cookies) } : {}),
      ...headers,
    },
    body,
    redirect: "manual",
  });
  mergeSetCookies(cookies, response);
  return response;
}

async function json(response) {
  return response.json();
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const ownerCookies = [fixture.ownerCookie];
const memberCookies = [fixture.memberCookie];
const ownerSessionResponse = await request("/api/v1/session", { cookies: ownerCookies });
const ownerSession = await json(ownerSessionResponse);
assert(ownerSessionResponse.status === 200 && ownerSession.authenticated, "Owner's seeded Laravel session was not accepted.");

const ownerDetailResponse = await request(`/api/v1/itineraries/${fixture.travelId}`, { cookies: ownerCookies });
const ownerDetail = await json(ownerDetailResponse);
assert(ownerDetailResponse.status === 200 && ownerDetail.permissions.can_manage_viewer_share, "Owner cannot read/manage the seeded itinerary.");
assert(ownerDetail.packing_items.some((item) => item.name === "Owner passport"), "Owner's personal packing item is missing.");

const memberDetailResponse = await request(`/api/v1/itineraries/${fixture.travelId}`, { cookies: memberCookies });
const memberDetail = await json(memberDetailResponse);
assert(memberDetailResponse.status === 200 && memberDetail.permissions.can_edit, "Member session cannot edit the shared itinerary.");
assert(memberDetail.packing_items.some((item) => item.name === "Member medicine"), "Member's personal packing item is missing.");
assert(!memberDetail.packing_items.some((item) => item.name === "Owner passport"), "Member received the owner's personal packing item.");

const existingAttachment = ownerDetail.plans.flatMap((plan) => plan.files)[0];
const existingFileResponse = await request(existingAttachment.url, { cookies: ownerCookies });
assert(existingFileResponse.status === 200, "Existing attachment could not be downloaded through the API.");
assert((await existingFileResponse.text()).includes("E2E attachment content"), "Existing attachment content did not match the isolated fixture.");
const sharedPdf = ownerDetail.plans.flatMap((plan) => plan.files).find((file) => file.file_name === "fixture.pdf");
assert(sharedPdf?.preview_url, "The supported fixture PDF did not expose a preview URL.");
const unauthenticatedPreview = await request(sharedPdf.preview_url);
assert(unauthenticatedPreview.status === 403, `Unauthenticated preview should be denied (received ${unauthenticatedPreview.status}).`);
const ownerPreview = await request(sharedPdf.preview_url, { cookies: ownerCookies });
assert(ownerPreview.status === 200, `Owner preview failed (received ${ownerPreview.status}).`);
assert(ownerPreview.headers.get("content-type")?.startsWith("application/pdf"), "Owner preview did not return PDF content type.");
assert(ownerPreview.headers.get("content-disposition")?.startsWith("inline;"), "Owner preview was not served inline.");
assert(ownerPreview.headers.get("x-content-type-options") === "nosniff", "Owner preview did not disable MIME sniffing.");
assert((await ownerPreview.text()).startsWith("%PDF-1.4"), "Owner preview bytes did not match the PDF fixture.");
const memberPreview = await request(sharedPdf.preview_url, { cookies: memberCookies });
assert(memberPreview.status === 200, `Member preview failed (received ${memberPreview.status}).`);
assert(memberPreview.headers.get("content-disposition")?.startsWith("inline;"), "Member preview was not served inline.");

const ownerCsrfResponse = await request("/sanctum/csrf-cookie", { cookies: ownerCookies });
assert(ownerCsrfResponse.ok, "Laravel did not issue a CSRF cookie to the owner.");
const ownerXsrfCookie = ownerCookies.find((cookie) => cookie.name === "XSRF-TOKEN");
assert(ownerXsrfCookie, "Laravel did not return the XSRF-TOKEN cookie.");
const ownerXsrfToken = decodeURIComponent(ownerXsrfCookie.value);

function itineraryPayload(title, clientId, withPlan = true) {
  return {
    title,
    overview_text: "Created through the real API over the same origin",
    template_type: null,
    plans: withPlan ? [{ client_id: clientId, date: null, time: null, title: "Uploaded plan", content: "Attachment proof", order: 0, existing_file_ids: [] }] : [],
    packing_items: [],
    souvenirs: [],
    notes: [],
  };
}

const rejectedPayload = new FormData();
rejectedPayload.append("payload", JSON.stringify(itineraryPayload("", "bad-plan")));
const csrfRejected = await request("/api/v1/itineraries", {
  cookies: ownerCookies,
  method: "POST",
  headers: { Accept: "application/json" },
  body: rejectedPayload,
});
assert(csrfRejected.status === 419, `A write without XSRF header should return 419 (received ${csrfRejected.status}).`);

const invalidPayload = new FormData();
invalidPayload.append("payload", JSON.stringify(itineraryPayload("", "invalid-plan")));
const validationFailure = await request("/api/v1/itineraries", {
  cookies: ownerCookies,
  method: "POST",
  headers: { Accept: "application/json", "X-XSRF-TOKEN": ownerXsrfToken },
  body: invalidPayload,
});
assert(validationFailure.status === 422, `Invalid itinerary should return 422 (received ${validationFailure.status}).`);

const uploadPayload = new FormData();
uploadPayload.append("payload", JSON.stringify(itineraryPayload("E2E uploaded trip", "uploaded-plan")));
uploadPayload.append("files[uploaded-plan][]", new Blob(["%PDF-1.4\nE2E upload"], { type: "application/pdf" }), "proof.pdf");
const pngBytes = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pXcAAAAASUVORK5CYII=", "base64");
uploadPayload.append("files[uploaded-plan][]", new Blob([pngBytes], { type: "image/png" }), "proof.png");
const uploadResponse = await request("/api/v1/itineraries", {
  cookies: ownerCookies,
  method: "POST",
  headers: { Accept: "application/json", "X-XSRF-TOKEN": ownerXsrfToken },
  body: uploadPayload,
});
assert(uploadResponse.status === 201, `Create with multipart upload failed (received ${uploadResponse.status}).`);
const uploadedItinerary = await json(uploadResponse);
assert(uploadedItinerary.plans[0]?.files[0]?.file_name === "proof.pdf", "Uploaded PDF was not attached to its plan.");
for (const [name, contentType, expectedBytes] of [
  ["proof.pdf", "application/pdf", Buffer.from("%PDF-1.4\nE2E upload")],
  ["proof.png", "image/png", pngBytes],
]) {
  const uploadedFile = uploadedItinerary.plans[0].files.find((file) => file.file_name === name);
  assert(uploadedFile?.preview_url, `Uploaded ${name} did not expose a preview URL.`);
  const previewResponse = await request(uploadedFile.preview_url, { cookies: ownerCookies });
  assert(previewResponse.status === 200, `Uploaded ${name} preview failed (received ${previewResponse.status}).`);
  assert(previewResponse.headers.get("content-type")?.startsWith(contentType), `Uploaded ${name} preview returned the wrong MIME type.`);
  assert(previewResponse.headers.get("content-disposition")?.startsWith("inline;"), `Uploaded ${name} was not served inline.`);
  assert(Buffer.from(await previewResponse.arrayBuffer()).equals(expectedBytes), `Uploaded ${name} preview bytes did not match.`);

  const downloadResponse = await request(uploadedFile.url, { cookies: ownerCookies });
  assert(downloadResponse.status === 200, `Uploaded ${name} could not be downloaded.`);
  assert(downloadResponse.headers.get("content-disposition")?.startsWith("attachment;"), `Uploaded ${name} download did not preserve attachment disposition.`);
  assert(Buffer.from(await downloadResponse.arrayBuffer()).equals(expectedBytes), `Uploaded ${name} download bytes did not match.`);
}

const guestCookies = [];
const guestCsrfResponse = await request("/sanctum/csrf-cookie", { cookies: guestCookies });
assert(guestCsrfResponse.ok, "Laravel did not start a guest session for shared access.");
const guestXsrfCookie = guestCookies.find((cookie) => cookie.name === "XSRF-TOKEN");
assert(guestXsrfCookie, "Guest session did not receive an XSRF-TOKEN cookie.");
const guestXsrfToken = decodeURIComponent(guestXsrfCookie.value);
const lockedDetail = await request(`/api/v1/itineraries/${fixture.travelId}`, { cookies: guestCookies });
assert(lockedDetail.status === 403, `Guest should be denied before the share password (received ${lockedDetail.status}).`);
const lockedPreview = await request(sharedPdf.preview_url, { cookies: guestCookies });
assert(lockedPreview.status === 403, `Locked guest preview should be denied (received ${lockedPreview.status}).`);
const unlockResponse = await request(`/api/v1/itineraries/${fixture.travelId}/shared-access`, {
  cookies: guestCookies,
  method: "POST",
  headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": guestXsrfToken },
  body: JSON.stringify({ shared_password: fixture.viewerPassword }),
});
assert(unlockResponse.status === 200, `Guest shared-access verification failed (received ${unlockResponse.status}).`);
const unlocked = await json(unlockResponse);
assert(unlocked.permissions.can_edit === false, "Shared viewer received edit permission.");
assert(unlocked.packing_items.length === 0, "Shared viewer received personal packing items.");
const sharedPreview = await request(sharedPdf.preview_url, { cookies: guestCookies });
assert(sharedPreview.status === 200, `Unlocked shared viewer preview failed (received ${sharedPreview.status}).`);
assert(sharedPreview.headers.get("content-disposition")?.startsWith("inline;"), "Shared viewer PDF was not served inline.");
assert((await sharedPreview.text()).startsWith("%PDF-1.4"), "Shared viewer PDF bytes did not match.");

const revokeResponse = await request(`/api/v1/itineraries/${fixture.travelId}/viewer-share`, {
  cookies: ownerCookies,
  method: "DELETE",
  headers: { Accept: "application/json", "X-XSRF-TOKEN": ownerXsrfToken },
});
assert(revokeResponse.ok, `Owner could not revoke viewer sharing (received ${revokeResponse.status}).`);
const revokedAccessResponse = await request(`/api/v1/itineraries/${fixture.travelId}`, { cookies: guestCookies });
assert(revokedAccessResponse.status === 403, `Revoked guest session still has access (received ${revokedAccessResponse.status}).`);
const revokedPreview = await request(sharedPdf.preview_url, { cookies: guestCookies });
assert(revokedPreview.status === 403, `Revoked guest can still preview attachment (received ${revokedPreview.status}).`);

console.log("API E2E passed: owner/member sessions, private packing, CSRF 419/422, create/upload/download, shared read-only access, and revocation.");
