import { mkdir, copyFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const root = new URL('../', import.meta.url);
const target = new URL('public/vendor/bootstrap/', root);
await mkdir(target, { recursive: true });
for (const [source, destination] of [
    ['css/bootstrap.min.css', 'bootstrap.min.css'],
    ['js/bootstrap.bundle.min.js', 'bootstrap.bundle.min.js'],
]) {
    await copyFile(new URL(`node_modules/bootstrap/dist/${source}`, root), new URL(destination, target));
}
await copyFile(new URL('node_modules/bootstrap/LICENSE', root), new URL('LICENSE', target));
console.log(`Bootstrap assets ready: ${fileURLToPath(target)}`);
