const path = require("path");
const fs = require("fs");
const { execSync } = require('child_process');

// 1. Determine the Project Root (Target)
const targetDir = process.argv[2] 
  ? path.resolve(process.argv[2]) 
  : (process.env.TARGET_DIR ? path.resolve(process.env.TARGET_DIR) : process.cwd());

// Only log the target directory once if run directly with output
if (process.env.npm_config_loglevel !== 'silent' && process.argv.length > 2) { // Check if TARGET_DIR was provided as an argument
    console.log(`[WPMoo] Building styles for: ${targetDir}`);
}

// 2. Find Sass
let sass;
try {
  sass = require(path.join(__dirname, "../node_modules/sass"));
} catch (e) {
  console.error("❌ Error: 'sass' module not found in wpmoo-cli. Please run 'npm install' in the wpmoo-cli directory.");
  process.exit(1);
}

// Find clean-css-cli
const cleanCssCliPath = path.join(__dirname, "../node_modules/.bin/cleancss");
if (!fs.existsSync(cleanCssCliPath)) {
  console.error("❌ Error: 'cleancss' executable not found. Please run 'npm install' in the wpmoo-cli directory.");
  process.exit(1);
}


// 3. Configuration
const isDevMode = process.env.DEV_MODE === 'true';
const themeColors = isDevMode ? ["amber"] : [
  "amber", "azure", "blue", "cyan", "fuchsia", "green", "grey", "indigo",
  "jade", "lime", "orange", "pink", "pumpkin", "purple", "red", "sand",
  "slate", "violet", "yellow", "zinc",
];

if (isDevMode && process.env.npm_config_loglevel !== 'silent') {
    console.log('[WPMoo] Dev Mode: Building only "amber" theme.');
}

const paths = {
  css: path.join(targetDir, "assets/css"),
  scss: path.join(targetDir, "resources/scss"),
  temp: path.join(targetDir, ".wpmoo-temp"),
  picoScopedCss: path.join(targetDir, "node_modules/@picocss/pico/css/pico.conditional.css"),
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
createFolderIfNotExists(paths.temp); // Create temp dir

const year = new Date().getFullYear();
const banner =
  `@charset "UTF-8";\n` +
  `/*!\n` +
  ` * WPMoo UI Scoped Base\n` +
  ` * Pico CSS ✨ v2.1.1 (https://picocss.com)\n` +
  ` * Copyright 2019-${year} - Licensed under MIT\n` +
  ` */\n`;

// 1. Get scoped Pico content first.
let scopedPicoContent = "";
try {
  const picoContent = fs.readFileSync(paths.picoScopedCss, "utf8");
  scopedPicoContent = picoContent
    .replace(/\.pico/g, ".wpmoo")
    .replace(/--pico-/g, "--wpmoo-")
    .replace(/^@charset "UTF-8";\s*/, "") // Remove Pico's charset
    .replace(/\/\*![\s\S]*?\*\/(\s*)?/g, ""); // Remove Pico's banner
} catch (e) {
  console.error(`❌ Error reading or scoping Pico CSS: ${e.message}`);
  process.exit(1);
}

themeColors.forEach((themeColor) => {
  const outputFileName = `wpmoo.${themeColor}.css`;
  const outputFilePath = path.join(paths.css, outputFileName);
  const outputMinFilePath = path.join(paths.css, `wpmoo.${themeColor}.min.css`);

  const tempScssContent =
    `@use "config/settings" with (\n` +
    `  $theme-color: "${themeColor}"\n` +
    `);\n` +
    `@use "wpmoo";\n`;
  
  const tempScssPath = path.join(paths.temp, `_temp_wpmoo_build_${themeColor}.scss`); // Use temp dir
  fs.writeFileSync(tempScssPath, tempScssContent);

  try {
    const result = sass.compile(tempScssPath, {
      style: "expanded",
      loadPaths: [ targetDir, paths.scss, path.join(__dirname, "../node_modules") ],
      quietDeps: true
    });

    let compiledCss = result.css.toString().replace(/^@charset "UTF-8";\s*/, "");
    compiledCss = compiledCss.replace(/\/\*![\s\S]*?\*\/(\s*)?/g, "");

    const finalCss = banner + scopedPicoContent + compiledCss;

    fs.writeFileSync(outputFilePath, finalCss);

    // Minify with clean-css, preserving the license comment (banner)
    const minifiedCss = execSync(`${cleanCssCliPath} -O2`, { input: finalCss }).toString();
    fs.writeFileSync(outputMinFilePath, minifiedCss);

  } catch (error) {
    console.error(`❌ Error compiling ${outputFileName}:`, error.message);
    process.exit(1);
  } finally {
    if (fs.existsSync(tempScssPath)) {
      fs.unlinkSync(tempScssPath);
    }
  }
});

// Clean up temp dir if empty (optional but nice)
try {
    if (fs.existsSync(paths.temp) && fs.readdirSync(paths.temp).length === 0) {
        fs.rmdirSync(paths.temp);
    }
} catch (e) {
    // Ignore cleanup errors
}

