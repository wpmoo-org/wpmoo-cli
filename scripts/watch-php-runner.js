const { spawn } = require('child_process');
const path = require('path');

const chokidarPath = path.join(__dirname, '../node_modules/.bin/chokidar');
const browserSyncPath = path.join(__dirname, '../node_modules/.bin/browser-sync');

const targetDir = process.env.TARGET_DIR;

if (!targetDir) {
    console.error("ERROR: TARGET_DIR environment variable is not set for watch-php-runner.js.");
    process.exit(1);
}

const command = [
    chokidarPath,
    `"${targetDir}/**/*.php"`, // Pattern for PHP files
    `"${targetDir}/index.html"`, // Pattern for index.html (if applicable)
    '--ignore', `"${targetDir}/node_modules/**"`, // Ignore node_modules
    '--ignore', `"${targetDir}/vendor/**"`, // Ignore vendor
    '--ignore', `"${targetDir}/.git/**"`, // Ignore .git
    '--quiet', // Suppress chokidar's own "Watching..." message
    '-c', `node -e "console.log('\n[PHP] Reloading...') && ${browserSyncPath} reload"`
];

// Spawn chokidar as a child process
const child = spawn(command[0], command.slice(1), { stdio: 'inherit', shell: true });

child.on('close', (code) => {
    if (code !== 0) {
        console.error(`watch-php-runner.js: chokidar exited with code ${code}`);
        process.exit(code);
    }
});
