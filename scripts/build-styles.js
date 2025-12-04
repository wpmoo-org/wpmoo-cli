const path = require("path");
const fs = require("fs");
const { execSync } = require('child_process');

// 1. Determine the Project Root (Target)
const targetDir = process.env.TARGET_DIR ? path.resolve(process.env.TARGET_DIR) : process.cwd();

// Only log the target directory once if run directly with output
if (process.env.npm_config_loglevel !== 'silent') {
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
const themeColors = [
  "amber", "azure", "blue", "cyan", "fuchsia", "green", "grey", "indigo",
  "jade", "lime", "orange", "pink", "pumpkin", "purple", "red", "sand",
  "slate", "violet", "yellow", "zinc",
];

const paths = {
  css: path.join(targetDir, "assets/css"),
  scss: path.join(targetDir, "resources/scss"),
  picoScopedCss: path.join(__dirname, "../node_modules/@picocss/pico/css/pico.conditional.css"),
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

const year = new Date().getFullYear();
const banner =
  `/*!
 * WPMoo UI Scoped Base
 * Copyright ${year} - Licensed under MIT
 * Contains portions of Pico CSS (MIT). See LICENSE-PICO.md.
 */
`;

// 1. Get scoped Pico content first.
let scopedPicoContent = "";
try {
  const picoContent = fs.readFileSync(paths.picoScopedCss, "utf8");
  scopedPicoContent = picoContent
    .replace(/\.pico/g, ".wpmoo")
    .replace(/--pico-/g, "--wpmoo-");
} catch (e) {
  console.error(`❌ Error reading or scoping Pico CSS: ${e.message}`);
  process.exit(1);
}

themeColors.forEach((themeColor) => {
  const outputFileName = `wpmoo.${themeColor}.css`;
  const outputFilePath = path.join(paths.css, outputFileName);
  const outputMinFilePath = path.join(paths.css, `wpmoo.${themeColor}.min.css`);

  const tempScssContent =
    `@use "resources/scss/config/settings" with (\n` +
    `  $theme-color: "${themeColor}"\n` +
    `);\n` +
    `@use "resources/scss/wpmoo";\n`;
  
  const tempScssPath = path.join(paths.scss, `_temp_wpmoo_build_${themeColor}.scss`);
  fs.writeFileSync(tempScssPath, tempScssContent);

  try {
    const result = sass.compile(tempScssPath, {
      style: "expanded",
      loadPaths: [ targetDir, path.join(__dirname, "../node_modules") ],
      quietDeps: true
    });

    let compiledCss = result.css.toString().replace(/^@charset "UTF-8";\s*/, "");
    compiledCss = compiledCss.replace(/\/\*![\s\S]*?\*\/(\s*)?/g, "");

    const finalCss = banner + scopedPicoContent + compiledCss;

    fs.writeFileSync(outputFilePath, finalCss);

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

