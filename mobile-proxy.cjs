/**
 * mobile-proxy.js
 * Reverse proxy: listens on 0.0.0.0:8090 → forwards to Herd's school-erp.test (127.0.0.1:80)
 * Run with: node mobile-proxy.js
 */
const http = require('http');
const net  = require('net');

const LISTEN_PORT = 8090;
const HERD_HOST   = '127.0.0.1';
const HERD_PORT   = 80;
const HERD_NAME   = 'school-erp.test';

const server = http.createServer((req, res) => {
  const options = {
    hostname: HERD_HOST,
    port:     HERD_PORT,
    path:     req.url,
    method:   req.method,
    headers:  { ...req.headers, host: HERD_NAME },
  };

  const proxy = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxy.on('error', (e) => {
    console.error('Proxy error:', e.message);
    res.writeHead(502);
    res.end('Bad Gateway');
  });

  req.pipe(proxy, { end: true });
});

server.listen(LISTEN_PORT, '0.0.0.0', () => {
  const ifaces = require('os').networkInterfaces();
  let wifiIp = 'unknown';
  for (const [, addrs] of Object.entries(ifaces)) {
    for (const a of addrs) {
      if (a.family === 'IPv4' && !a.internal) { wifiIp = a.address; break; }
    }
  }
  console.log(`Mobile proxy running:`);
  console.log(`  Local  : http://localhost:${LISTEN_PORT}/api`);
  console.log(`  Network: http://${wifiIp}:${LISTEN_PORT}/api`);
  console.log(`  Herd   : http://${HERD_NAME} → ${HERD_HOST}:${HERD_PORT}`);
});
