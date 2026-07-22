import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const backendDir = path.join(rootDir, 'backend');
const distDir = path.join(rootDir, 'dist');
const outputDir = path.join(rootDir, '.deploy', 'hostinger');
const publicHtmlDir = path.join(outputDir, 'public_html');
const apiPublicDir = path.join(publicHtmlDir, 'api');
const laravelAppDir = path.join(outputDir, 'laravel-pos');

const laravelIgnore = [
  '.env',
  'vendor',
  'node_modules',
  'storage/logs',
  'storage/framework/cache',
  'storage/framework/sessions',
  'storage/framework/testing',
  'storage/framework/views',
  '.phpunit.cache',
  '.phpunit.result.cache',
];

async function exists(targetPath) {
  try {
    await readFile(targetPath);
    return true;
  } catch {
    return false;
  }
}

async function copyDir(source, destination) {
  await cp(source, destination, {
    recursive: true,
    force: true,
    filter: (src) => {
      const relative = path.relative(backendDir, src).replace(/\\/g, '/');
      return !laravelIgnore.some((ignored) => relative === ignored || relative.startsWith(`${ignored}/`));
    },
  });
}

async function main() {
  if (!(await exists(path.join(distDir, 'index.html')))) {
    throw new Error('Missing dist/index.html. Run "npm run build" first.');
  }

  await rm(outputDir, { recursive: true, force: true });
  await mkdir(apiPublicDir, { recursive: true });

  await cp(distDir, publicHtmlDir, { recursive: true, force: true });
  await copyDir(backendDir, laravelAppDir);
  await cp(path.join(backendDir, 'public'), apiPublicDir, { recursive: true, force: true });

  const apiIndexPath = path.join(apiPublicDir, 'index.php');
  const apiIndexContents = `<?php

use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;

define('LARAVEL_START', microtime(true));

$appBasePath = __DIR__.'/../../laravel-pos';

if (file_exists($maintenance = $appBasePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBasePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appBasePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
`;

  await writeFile(apiIndexPath, apiIndexContents);

  const readmePath = path.join(outputDir, 'README_HOSTINGER_UPLOAD.txt');
  const readmeContents = `Hostinger upload bundle
======================

Generated on: ${new Date().toISOString()}

Upload targets:
1. Upload everything inside "public_html" to your domain's public_html folder.
2. Upload the "laravel-pos" folder beside public_html in your hosting account home.

Expected server layout:
- ~/domains/8shinerice.com/public_html
- ~/laravel-pos

After upload, run in SSH:
  cd ~/laravel-pos
  composer install --no-dev --optimize-autoloader
  php artisan key:generate
  php artisan migrate --force --seed
  php artisan config:cache
  php artisan route:cache

Make sure backend/.env values are set for production before uploading.
`;

  await writeFile(readmePath, readmeContents);
  console.log(`Hostinger bundle prepared at ${outputDir}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
