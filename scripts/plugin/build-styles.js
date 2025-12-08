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
  temp: path.join(targetDir, ".wpmoo-temp"),
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

    // Read the original main.scss content to check for imports
    let originalContent = fs.readFileSync(mainScssPath, 'utf8');

    // Check if the main.scss imports framework source files (which would cause conflicts)
    const importsFrameworkSource = originalContent.includes('vendor/wpmoo/wpmoo/resources/scss');

    if (importsFrameworkSource) {
        // The plugin is trying to import the framework's SCSS source, which can cause
        // module configuration conflicts if the plugin also has its own config files.
        // We need to handle this carefully to avoid the "already loaded, so it can't be configured" error
        console.error("❌ The plugin's main.scss is trying to import the framework's SCSS source directly.");
        console.error("This can cause module configuration conflicts.");
        console.error("Use the compiled CSS instead: @import 'vendor/wpmoo/wpmoo/assets/css/wpmoo.amber.css';");
        process.exit(1);
    } else {
        // Check if the main.scss imports framework CSS files
        const frameworkCssImportRegex = /@import\s+['"]vendor\/wpmoo\/wpmoo\/assets\/css\/wpmoo\.[^'"]+\.css['"];/g;
        const matches = originalContent.match(frameworkCssImportRegex);
        if (matches) {
            // If importing the framework's CSS, we need to inline the content so scoping can work
            let processedContent = originalContent;

            // Process each framework CSS import
            for (const importStatement of matches) {
                const cssFileMatch = importStatement.match(/['"]vendor\/wpmoo\/wpmoo\/assets\/css\/([^'"]+\.css)['"]/);

                if (cssFileMatch) {
                    const cssFileName = cssFileMatch[1];
                    const frameworkCssPath = path.join(targetDir, 'vendor', 'wpmoo', 'wpmoo', 'assets', 'css', cssFileName);

                    if (fs.existsSync(frameworkCssPath)) {
                        const frameworkCssContent = fs.readFileSync(frameworkCssPath, 'utf8');
                        processedContent = processedContent.replace(importStatement, frameworkCssContent);
                    } else {
                        console.error(`❌ Framework CSS file not found: ${frameworkCssPath}`);
                        process.exit(1);
                    }
                }
            }

            // Write a temporary file with the inlined content
            const tempScssPath = path.join(paths.temp, '_temp_inline_imports.scss');
            createFolderIfNotExists(paths.temp);
            fs.writeFileSync(tempScssPath, processedContent);

            try {
                const result = sass.compile(tempScssPath, {
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

                // Scope the plugin's styles to avoid conflicts with other plugins using WPMoo
                // The framework renders HTML with .wpmoo classes, so we need to scope the CSS
                // to match the plugin's context for proper isolation
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
            } finally {
                // Clean up temp file
                if (fs.existsSync(tempScssPath)) {
                    fs.unlinkSync(tempScssPath);
                }
            }
        } else {
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

                // Scope the plugin's styles to avoid conflicts with other plugins using WPMoo
                // The framework renders HTML with .wpmoo classes, so we need to scope the CSS
                // to match the plugin's context for proper isolation
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
        }
    }
} else {
    if (!quietBuild) {
        console.log(`[WPMoo] No main.scss found in ${paths.scss}. Skipping custom style build.`);
    }
}

// Clean up temp dir if empty (optional but nice)
try {
    if (fs.existsSync(paths.temp) && fs.readdirSync(paths.temp).length === 0) {
        fs.rmdirSync(paths.temp);
    }
} catch (e) {
    // Ignore cleanup errors
}
