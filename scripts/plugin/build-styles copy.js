const path = require("path");
const fs = require("fs");
const { execSync } = require('child_process');
const yaml = require('js-yaml');

// 1. Determine the Project Root (Target)
const targetDir = process.argv[2] 
  ? path.resolve(process.argv[2]) 
  : (process.env.TARGET_DIR ? path.resolve(process.env.TARGET_DIR) : process.cwd());

// Only log the target directory once if run directly with output
const quietBuild = process.env.WPMOO_QUIET_BUILD === 'true';

if (!quietBuild && process.env.npm_config_loglevel !== 'silent' && process.argv.length > 2) { // Check if TARGET_DIR was provided as an argument
    console.log(`[WPMoo] Building styles for: ${targetDir}`);
}

// 2. Find Sass
let sass;
try {
  sass = require(path.join(__dirname, "../../node_modules/sass"));
} catch (e) {
  console.error("❌ Error: 'sass' module not found in wpmoo-cli. Please run 'npm install' in the wpmoo-cli directory.");
  process.exit(1);
}

// Find clean-css-cli
const cleanCssCliPath = path.join(__dirname, "../../node_modules/.bin/cleancss");
if (!fs.existsSync(cleanCssCliPath)) {
  console.error("❌ Error: 'cleancss' executable not found. Please run 'npm install' in the wpmoo-cli directory.");
  process.exit(1);
}


// 3. Configuration
const isDevMode = process.env.DEV_MODE === 'true';

let textDomain = 'wpmoo'; // Default
try {
    const configFile = fs.readFileSync(path.join(targetDir, 'wpmoo-config.yml'), 'utf8');
    const config = yaml.load(configFile);
    if (config && config.project && config.project.text_domain) {
        textDomain = config.project.text_domain;
    }
} catch (e) {
    // Config file might not exist, proceed with default
}

const paths = {
  css: path.join(targetDir, "assets/css"),
  scss: path.join(targetDir, "resources/scss"),
};

const createFolderIfNotExists = (foldername) => {
  if (!fs.existsSync(foldername)) {
    fs.mkdirSync(foldername, { recursive: true });
  }
};

if (!fs.existsSync(paths.scss)) {
  // Silently exit if the directory doesn't exist, as it might be a project without styles.
  process.exit(0);
}

createFolderIfNotExists(paths.css);


// --- Build Logic ---

// This is a user project, build their main.scss
const mainScssPath = path.join(paths.scss, 'main.scss');
if (fs.existsSync(mainScssPath)) {
    if (!quietBuild) {
        console.log(`[WPMoo] Building custom stylesheet: ${mainScssPath}`);
    }
    const outputFileName = `${textDomain}.css`;
    const outputFilePath = path.join(paths.css, outputFileName);
    const outputMinFilePath = path.join(paths.css, `${textDomain}.min.css`);

    try {
        const result = sass.compile(mainScssPath, {
            style: "expanded",
            loadPaths: [ 
                targetDir, 
                paths.scss, 
                path.join(targetDir, 'vendor', 'wpmoo', 'wpmoo', 'resources', 'scss'),
                path.join(__dirname, '../../node_modules')
            ],
            quietDeps: true
        });

        let compiledCss = result.css.toString().replace(/^@charset "UTF-8";\s*/, "");
        
        // The user's file already imports the scoped base, so we don't add it again.
        // We just need to scope the classes from the framework.
        const prefix = textDomain;
        let finalCss = compiledCss
            .replace(/\.wpmoo/g, `.${prefix}`)
            .replace(/--wpmoo-/g, `--${prefix}-`);

        fs.writeFileSync(outputFilePath, finalCss);

        // Minify with clean-css only in production mode
        if (!isDevMode) {
            const minifiedCss = execSync(`${cleanCssCliPath} -O2`, { input: finalCss }).toString();
            fs.writeFileSync(outputMinFilePath, minifiedCss);
        }
    } catch (error) {
        console.error(`❌ Error compiling ${outputFileName}:`, error.message);
        process.exit(1);
    }
} else {
    if (!quietBuild) {
        console.log(`[WPMoo] No main.scss found in ${paths.scss}. Skipping custom style build.`);
    }
}
