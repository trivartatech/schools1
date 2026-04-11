const path = require('path');
const port = process.env.PORT || 8090;
require('child_process').execFileSync('npx', ['expo', 'start', '--web', '--port', String(port)], {
  stdio: 'inherit',
  cwd: path.join(__dirname, 'educonnect'),
  shell: true,
});
