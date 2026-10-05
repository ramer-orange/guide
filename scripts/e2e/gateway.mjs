import http from "node:http";

const laravel = new URL(process.env.E2E_LARAVEL_ORIGIN ?? "http://127.0.0.1:8000");
const next = new URL(process.env.E2E_NEXT_ORIGIN ?? "http://127.0.0.1:3000");
const port = Number(process.env.E2E_GATEWAY_PORT ?? 8081);

function isNextPath(pathname) {
  return pathname.startsWith("/_next/")
    || pathname.startsWith("/images/")
    || pathname === "/"
    || pathname === "/policy"
    || pathname === "/terms"
    || pathname === "/itineraries/index"
    || pathname === "/itineraries/create"
    || /^\/itineraries\/[^/]+\/(edit|shared-access)$/.test(pathname);
}

const server = http.createServer((request, response) => {
  if (request.url === "/__e2e/health") {
    response.writeHead(200, { "content-type": "text/plain" }).end("ok");
    return;
  }

  const target = new URL(isNextPath(new URL(request.url, "http://gateway.local").pathname) ? next : laravel);
  const upstream = http.request({
    protocol: target.protocol,
    hostname: target.hostname,
    port: target.port,
    method: request.method,
    path: request.url,
    headers: {
      ...request.headers,
      host: request.headers.host,
      "x-forwarded-host": request.headers.host,
      "x-forwarded-proto": "http",
      "x-forwarded-for": request.socket.remoteAddress,
    },
  }, (upstreamResponse) => {
    response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
    upstreamResponse.pipe(response);
  });

  upstream.on("error", (error) => {
    response.writeHead(502, { "content-type": "text/plain" });
    response.end(`E2E gateway upstream error: ${error.message}`);
  });
  request.pipe(upstream);
});

server.listen(port, "127.0.0.1", () => {
  process.stdout.write(`E2E gateway listening on http://127.0.0.1:${port}\n`);
});

for (const signal of ["SIGINT", "SIGTERM"]) {
  process.on(signal, () => server.close(() => process.exit(0)));
}
