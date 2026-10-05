import { spawn, spawnSync } from "node:child_process";
import { cpSync, existsSync } from "node:fs";
import { access, writeFile as writeFileAsync, mkdir as mkdirAsync } from "node:fs/promises";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "../..");
const databasePath = resolve(root, "database/e2e.sqlite");
const fixturePath = resolve(root, "storage/app/e2e-fixture.json");
const baseURL = process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081";
const base = new URL(baseURL);
const testKey = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";
const env = {
  ...process.env,
  APP_ENV: "e2e",
  APP_KEY: testKey,
  APP_URL: base.origin,
  APP_DEBUG: "true",
  DB_CONNECTION: "sqlite",
  DB_DATABASE: databasePath,
  DATABASE_URL: "",
  SESSION_DRIVER: "file",
  SESSION_COOKIE: "plagine_e2e_session",
  SESSION_ENCRYPT: "false",
  SESSION_SECURE_COOKIE: "false",
  SESSION_DOMAIN: "",
  SESSION_SAME_SITE: "lax",
  SANCTUM_STATEFUL_DOMAINS: `${base.host},localhost:${base.port}`,
  CACHE_STORE: "file",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "log",
  FILESYSTEM_DISK: "local",
  FILESYSTEM_UPLOADS_DISK: "public",
  E2E_BASE_URL: base.origin,
  E2E_FIXTURE_PATH: fixturePath,
  E2E_GATEWAY_PORT: base.port || "80",
  PORT: "3000",
  HOSTNAME: "127.0.0.1",
};

try {
  await access(resolve(root, "vendor/autoload.php"));
  await access(resolve(root, "frontend/node_modules/.bin/playwright"));
} catch {
  throw new Error("Install PHP Composer dependencies and run npm ci --prefix frontend before E2E.");
}

await mkdirAsync(resolve(root, "storage/app"), { recursive: true });
await mkdirAsync(process.env.E2E_SCREENSHOT_DIR ?? "/tmp/guide-next-e2e", { recursive: true });
await writeFileAsync(databasePath, "");
run("php", ["artisan", "migrate:fresh", "--force"]);
await seedFixtures();
run("npm", ["--prefix", "frontend", "run", "build"]);

const children = [];
function start(command, args, cwd = root) {
  const child = spawn(command, args, { cwd, env, stdio: "inherit", detached: process.platform !== "win32" });
  children.push(child);
  return child;
}

async function stopChildren() {
  const active = children.filter((child) => child.exitCode === null);
  for (const child of active) {
    try {
      if (process.platform === "win32") child.kill("SIGTERM");
      else process.kill(-child.pid, "SIGTERM");
    } catch {}
  }
  await Promise.race([
    Promise.all(active.map((child) => new Promise((resolveExit) => child.once("exit", resolveExit)))),
    new Promise((resolveTimeout) => setTimeout(resolveTimeout, 3_000)),
  ]);
  for (const child of active) {
    if (child.exitCode !== null) continue;
    try {
      if (process.platform === "win32") child.kill("SIGKILL");
      else process.kill(-child.pid, "SIGKILL");
    } catch {}
  }
  children.length = 0;
}

process.once("SIGINT", async () => { await stopChildren(); process.exit(130); });
process.once("SIGTERM", async () => { await stopChildren(); process.exit(143); });

try {
  startServices();
  await waitFor("/__e2e/health", (response) => response.status === 200);
  await waitFor("/api/v1/session", (response) => response.status === 200);
  await waitFor("/_next/static", (response) => response.status !== 502);
  run("node", ["scripts/e2e/verify-api.mjs"]);
  await stopChildren();

  // The API pass revokes the seeded share. Recreate isolated fixtures before
  // the browser pass so each browser scenario starts from the original state.
  run("php", ["artisan", "migrate:fresh", "--force"]);
  await seedFixtures();
  startServices();
  await waitFor("/__e2e/health", (response) => response.status === 200);
  await waitFor("/api/v1/session", (response) => response.status === 200);
  await waitFor("/_next/static", (response) => response.status !== 502);
  run("npm", ["--prefix", "frontend", "run", "e2e"]);
} finally {
  await stopChildren();
}

function startServices() {
  const standaloneCandidates = [
    resolve(root, "frontend/.next/standalone/server.js"),
    resolve(root, "frontend/.next/standalone/frontend/server.js"),
  ];
  const standaloneServer = standaloneCandidates.find((candidate) => {
    return existsSync(candidate);
  });
  if (!standaloneServer) throw new Error("Next standalone server was not produced by the build.");
  const standaloneRoot = resolve(root, "frontend/.next/standalone");
  cpSync(resolve(root, "frontend/.next/static"), resolve(standaloneRoot, ".next/static"), { recursive: true, force: true });
  cpSync(resolve(root, "frontend/public"), resolve(standaloneRoot, "public"), { recursive: true, force: true });
  start("php", ["artisan", "serve", "--host=127.0.0.1", "--port=8000"]);
  start("node", ["server.js"], standaloneRoot);
  start("node", ["scripts/e2e/gateway.mjs"]);
}

async function seedFixtures() {
  const seeded = spawnSync("php", ["scripts/e2e/seed.php"], { cwd: root, env, encoding: "utf8" });
  if (seeded.status !== 0) throw new Error(seeded.stderr || "Could not seed the isolated E2E database.");
  await writeFileAsync(fixturePath, seeded.stdout);
}

function run(command, args) {
  const result = spawnSync(command, args, { cwd: root, env, stdio: "inherit" });
  if (result.status !== 0) throw new Error(`${command} ${args.join(" ")} failed with status ${result.status}.`);
}

async function waitFor(path, ready) {
  const deadline = Date.now() + 60_000;
  let lastError;
  while (Date.now() < deadline) {
    const stopped = children.find((child) => child.exitCode !== null);
    if (stopped) throw new Error(`A local E2E server exited early with status ${stopped.exitCode}.`);
    try {
      const response = await fetch(new URL(path, base));
      if (ready(response)) return;
    } catch (error) {
      lastError = error;
    }
    await new Promise((resolveWait) => setTimeout(resolveWait, 500));
  }
  throw new Error(`Timed out waiting for ${path}${lastError ? `: ${lastError.message}` : ""}`);
}
