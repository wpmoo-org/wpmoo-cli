const path = require("path");
const fs = require("fs");

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
    if (fs.existsSync(p)) {
      sass = require(p);
      break;
    }
  } catch (e) { }
}

if (!sass) {
  console.error("❌ Error: 'sass' module not found.");
  console.error("Please run 'npm install' in your project directory or ensure 'wpmoo/wpmoo' has dependencies installed.");
  process.exit(1);
}

// 3. Configuration
const themeColors = [
  "amber", "azure", "blue", "cyan", "fuchsia", "green", "grey", "indigo",
  "jade", "lime", "orange", "pink", "pumpkin", "purple", "red", "sand",
  "slate", "violet", "yellow", "zinc",
];

const paths = {
  temp: path.join(targetDir, ".pico-build-tmp"),
  css: path.join(targetDir, "assets/css"),
  scss: path.join(targetDir, "resources/scss"),
  node_modules: path.join(targetDir, "node_modules"),
  monorepo_modules: path.join(__dirname, "../../wpmoo/node_modules"),
};

const createFolderIfNotExists = (foldername) => {
  if (!fs.existsSync(foldername)) {
    fs.mkdirSync(foldername, { recursive: true });
  }
};

const emptyFolder = (foldername) => {
  if (fs.existsSync(foldername)) {
    fs.readdirSync(foldername).forEach((file) => {
      fs.unlinkSync(path.join(foldername, file));
    });
  }
};

if (!fs.existsSync(paths.scss)) {
  console.error(`❌ Error: Resources directory not found at ${paths.scss}`);
  process.exit(1);
}

createFolderIfNotExists(paths.temp);
createFolderIfNotExists(paths.css);
emptyFolder(paths.temp);

const clearLine = () => {
  if (process.stdout.isTTY) {
    process.stdout.clearLine();
    process.stdout.cursorTo(0);
  }
};

console.log(`[WPMoo] Building styles...`);

const year = new Date().getFullYear();
const currentYear = new Date().getFullYear();
const picoCopyrightYear = (currentYear > 2019) ? `2019-${currentYear}` : '2019'; // Assuming Pico started in 2019

const banner =
  `/*!
 * WPMoo Framework Scoped Base
 * Pico CSS ✨ v2.1.1 (https://picocss.com)
 * Copyright 2019-2025 - Licensed under MIT
 */
`;

themeColors.forEach((themeColor, colorIndex) => {
  const versions = [
    {
      name: "wpmoo",
      content:
        '@use "../resources/scss/config/settings" with (\n' +
        '  $theme-color: "' + themeColor + '"\n' +
        ');\n' +
        '@use "../resources/scss/wpmoo";\n',
    },
  ];

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

  versions.forEach((version) => {
    const fileName = `${version.name}.${themeColor}`;
    const tempFile = path.join(paths.temp, `${fileName}.scss`);

    fs.writeFileSync(tempFile, version.content);

    try {
      // 1. Compile Expanded (Normal CSS)
      const resultExpanded = sass.compile(tempFile, {
        style: "expanded",
        loadPaths: [
          paths.node_modules,
          paths.monorepo_modules,
          paths.scss
        ],
        quietDeps: true
      });

      // Remove existing @charset, specific comments, and any existing banners from Sass output
      let cssExpanded = resultExpanded.css;
      cssExpanded = cssExpanded.replace(/^@charset "UTF-8";\s*/, "");
      cssExpanded = cssExpanded.replace(/\/\* WP Moo SCSS customizations \*\/\s*/g, "");
      cssExpanded = cssExpanded.replace(/\/\* Import Pico SCSS using variables from sibling modules \*\/\s*/g, "");
      cssExpanded = cssExpanded.replace(/\/\* WPMoo CSS custom property defaults \(scoped\) \*\/\s*/g, "");
      cssExpanded = cssExpanded.replace(/\/\*!([\s\S]*?)\*\/\s*/g, ""); // Remove any other existing banners (like Pico's own banner)

      fs.writeFileSync(path.join(paths.css, `${fileName}.css`), banner + cssExpanded);

      // 2. Compile Compressed (Minified CSS)
      const resultCompressed = sass.compile(tempFile, {
        style: "compressed",
        loadPaths: [
          paths.node_modules,
          paths.monorepo_modules,
          paths.scss
        ],
        quietDeps: true
      });

      let cssCompressed = resultCompressed.css;
      // Minified CSS usually strips all comments except /*! comments.
      // We just ensure our banner is on top.
      if (cssCompressed.startsWith('\uFEFF')) { // BOM check
        cssCompressed = cssCompressed.slice(1);
      }
      if (cssCompressed.startsWith('@charset "UTF-8";')) {
        cssCompressed = cssCompressed.replace('@charset "UTF-8";', "");
      }
      // Even in compressed, these might somehow appear or be part of content. Clean them.
      cssCompressed = cssCompressed.replace(/\/\* WP Moo SCSS customizations \*\/\s*/g, "");
      cssCompressed = cssCompressed.replace(/\/\* Import Pico SCSS using variables from sibling modules \*\/\s*/g, "");
      cssCompressed = cssCompressed.replace(/\/\* WPMoo CSS custom property defaults \(scoped\) \*\/\s*/g, "");
      cssCompressed = cssCompressed.replace(/\/\*!([\s\S]*?)\*\/\s*/g, ""); // Remove any other existing banners (like Pico's own banner)

      fs.writeFileSync(path.join(paths.css, `${fileName}.min.css`), banner + cssCompressed);

    } catch (error) {
      clearLine();
      console.error(`❌ Error compiling ${fileName}:`, error.message);
    }
  });
});

emptyFolder(paths.temp);
fs.rmdirSync(paths.temp);

clearLine();
console.log("[WPMoo] Styles built successfully! 🎨");
