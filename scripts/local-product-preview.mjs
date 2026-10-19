// Loopback-only demonstration using a new private SQLite database, never .env.
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, mkdtemp, writeFile } from 'node:fs/promises';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
async function free(port) {
    return new Promise(resolve => {
        const probe = net.createServer();
        probe.once('error', () => resolve(false));
        probe.listen(port, '127.0.0.1', () => probe.close(() => resolve(true)));
    });
}
let port = 8014;
while (!(await free(port))) { if (++port > 8030) throw new Error('No available preview port'); }
const privateRoot = path.join(workspace, 'storage/app/private');
await mkdir(privateRoot, { recursive: true });
const root = await mkdtemp(path.join(privateRoot, 'student-workflow-browser-'));
await writeFile(path.join(root, '.isolated-student-browser'), 'local demonstration only');
await writeFile(path.join(root, 'clock.txt'), '2026-10-18 12:00:00');
const env = { ...process.env, SPACEMATCH_STUDENT_BROWSER_ROOT: root,
    SPACEMATCH_STUDENT_BROWSER_KEY: `base64:${randomBytes(32).toString('base64')}`,
    SPACEMATCH_STUDENT_BROWSER_URL: `http://127.0.0.1:${port}` };
const php = process.env.PHP_BINARY ?? 'php';
async function run(script) {
    await new Promise((resolve, reject) => {
        const child = spawn(php, [script], { cwd: workspace, env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        let output = '';
        child.stdout.on('data', chunk => { output += chunk; });
        child.stderr.on('data', chunk => { output += chunk; });
        child.once('error', reject);
        child.once('exit', code => code === 0 ? resolve() : reject(new Error(output)));
    });
}
await run('scripts/student-browser-fixtures.php');
await run('scripts/local-product-preview-fixtures.php');
const server = spawn(php, ['-S', `127.0.0.1:${port}`, 'scripts/student-browser-router.php'], { cwd: workspace, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
server.stderr.on('data', () => {});
server.once('error', error => { console.error(error.message); process.exitCode = 1; });
server.once('exit', code => { process.exitCode = code ?? 0; });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill());
console.log(`Local demonstration: http://127.0.0.1:${port}`);
console.log('Synthetic accounts: student, other, owner, admin, master-admin @student-browser.example.test');
console.log('Synthetic password for these accounts only: Browser-Student-2026!');
console.log('New isolated SQLite data; no existing database or production connection. Keep this process running.');
