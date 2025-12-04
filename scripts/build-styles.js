const path = require("path");
const fs = require("fs");
const { execSync } = require('child_process');

// 1. Determine the Project Root (Target)
const targetDir = process.argv[2] ? path.resolve(process.argv[2]) : process.cwd();

console.log(`[WPMoo] Target Directory: ${targetDir}`);

// 2. Find Sass
let sass;
const potentialSassPaths = [
  path.join(targetDir, "node_modules", "sass"),
  path.join(__dirname, "../../wpmoo/node_modules/sass"), // Monorepo dev path
  path.join(__dirname, "../node_modules/sass")
];

for (const p of potentialSassPaths) {
  try {
    // Only require if it's a directory (i.e., a package)
    if (fs.existsSync(p) && fs.statSync(p).isDirectory()) {
      sass = require(p);
      break;
    }
  } catch (e) { }
}

if (!sass) {
  console.error("❌ Error: 'sass' module not found.");
  console.error("Please run 'npm install' in wpmoo-cli directory or ensure 'sass' is installed.");
  process.exit(1);
}

// Find clean-css-cli (should be in wpmoo-cli's node_modules)
const cleanCssCliPath = path.join(__dirname, "../node_modules/.bin/cleancss");
if (!fs.existsSync(cleanCssCliPath)) {
  console.error("❌ Error: 'cleancss' executable not found.");
  console.error("Please run 'npm install' in wpmoo-cli directory.");
  process.exit(1);
}


// 3. Configuration
const themeColors = [
  "amber", "azure", "blue", "cyan", "fuchsia", "green", "grey", "indigo",
  "jade", "lime", "orange", "pink", "pumpkin", "purple", "red", "sand",
  "slate", "violet", "yellow", "zinc",
];

const paths = {
  // We'll use targetDir for the project-specific paths
  css: path.join(targetDir, "assets/css"),
  scss: path.join(targetDir, "resources/scss"),
  node_modules: path.join(targetDir, "node_modules"),
  monorepo_modules: path.join(__dirname, "../../wpmoo/node_modules"), // For shared monorepo node_modules
  picoScopedCss: path.join(__dirname, "../node_modules/@picocss/pico/css/pico.conditional.css"), // Pico CSS from wpmoo-cli's node_modules
};

const createFolderIfNotExists = (foldername) => {
  if (!fs.existsSync(foldername)) {
    fs.mkdirSync(foldername, { recursive: true });
  }
};

if (!fs.existsSync(paths.scss)) {
  console.error(`❌ Error: Resources directory not found at ${paths.scss}`);
  process.exit(1);
}

createFolderIfNotExists(paths.css);

const clearLine = () => {
  if (process.stdout.isTTY) {
    process.stdout.clearLine();
    process.stdout.cursorTo(0);
  }
};

console.log(`[WPMoo] Building styles...`);

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


themeColors.forEach((themeColor, colorIndex) => {
  const displayAsciiProgress = ({ length, index, color }) => {
    if (!process.stdout.isTTY) return;
    const progress = Math.round(((index + 1) / length) * 100);
    const bar = "■".repeat(Math.floor(progress / 10));
    const empty = "□".repeat(10 - Math.floor(progress / 10));
    process.stdout.write(`[WPMoo] ✨ ${bar}${empty} ${color} (${progress}%)\r`);
  };

  displayAsciiProgress({
    length: themeColors.length,
    index: colorIndex,
    color: themeColor.charAt(0).toUpperCase() + themeColor.slice(1),
  });

  const scssEntryFile = path.join(paths.scss, "wpmoo.scss");
  const outputFileName = `wpmoo.${themeColor}.css`;
  const outputFilePath = path.join(paths.css, outputFileName);
  const outputMinFilePath = path.join(paths.css, `wpmoo.${themeColor}.min.css`);


  // Temporarily create a SCSS file that includes our themed settings
  const tempScssContent =
    `@use "resources/scss/config/settings" with (\n` +
    `  $theme-color: "${themeColor}"\n` +
    `);\n` +
    `@use "resources/scss/wpmoo";\n`;

  // Create a temporary SCSS file to compile
  const tempScssPath = path.join(paths.scss, `_temp_wpmoo_build_${themeColor}.scss`);
  fs.writeFileSync(tempScssPath, tempScssContent);


  try {
    // 1. Compile SCSS
    const result = sass.compile(tempScssPath, {
      style: "expanded", // Always compile expanded first for consistency
      loadPaths: [
        paths.node_modules, // Project's node_modules
        path.join(__dirname, "../node_modules"), // CLI's node_modules
        paths.scss // Project's scss folder
      ],
      quietDeps: true
    });

    let compiledCss = result.css.toString();

    // Remove specific comments and @charset from Sass output
    compiledCss = compiledCss.replace(/^@charset "UTF-8";\s*/, "");
    compiledCss = compiledCss.replace(/\/\* WP Moo SCSS customizations \*\/\s*/g, "");
    compiledCss = compiledCss.replace(/\/\* Import Pico SCSS using variables from sibling modules \*\/\s*/g, "");
    compiledCss = compiledCss.replace(/\/\* WPMoo CSS custom property defaults \(scoped\) \*\/\s*/g, "");
    // Remove any existing banners (like Pico's own banner) if present in the compiled CSS
    compiledCss = compiledCss.replace(/\/\*![\s\S]*?\*\/(\s*)?/g, "");

    // Final CSS (Prepending scoped Pico and banner)
    const finalCss = banner + scopedPicoContent + compiledCss;

    // Write unminified CSS
    fs.writeFileSync(outputFilePath, finalCss);

    // Minify using clean-css-cli
    const minifiedCss = execSync(`${cleanCssCliPath} --skip-rebase -O2`, { input: finalCss }).toString();
    fs.writeFileSync(outputMinFilePath, minifiedCss);

  } catch (error) {
    clearLine();
    console.error(`❌ Error compiling ${outputFileName}:`, error.message);
    process.exit(1);
  } finally {
    // Clean up temporary SCSS file
    if (fs.existsSync(tempScssPath)) {
      fs.unlinkSync(tempScssPath);
    }
  }
});

clearLine();
console.log("[WPMoo] Styles built successfully! 🎨");

