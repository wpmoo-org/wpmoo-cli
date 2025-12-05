const { spawn } = require('child_process');
const path = require('path');

const chokidarPath = path.join(__dirname, '../node_modules/.bin/chokidar');
const buildStylesScript = path.join(__dirname, './build-styles.js');

const targetDir = process.env.TARGET_DIR;

if (!targetDir) {
    console.error("ERROR: TARGET_DIR environment variable is not set for watch-styles-runner.js.");
    process.exit(1);
}

const command = [
    chokidarPath,
    `"${targetDir}/resources/scss/**/*.scss"`, // Pattern with TARGET_DIR
    '--quiet', // Suppress chokidar's own "Watching..." message
    '-c', `node "${buildStylesScript}" "${targetDir}"`, // Command to run on change
];

// Spawn chokidar as a child process
const child = spawn(command[0], command.slice(1), { stdio: 'inherit', shell: true });

child.on('close', (code) => {
    if (code !== 0) {
        console.error(`watch-styles-runner.js: chokidar exited with code ${code}`);
        process.exit(code);
    }
});
