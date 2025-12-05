const path = require("path");
const fs = require("fs");

// 1. Determine the Project Root (Target)
const targetDir = process.argv[2] 
  ? path.resolve(process.argv[2]) 
  : (process.env.TARGET_DIR ? path.resolve(process.env.TARGET_DIR) : process.cwd());

// Only log the target directory once if run directly with output
const quietBuild = process.env.WPMOO_QUIET_BUILD === 'true';

if (!quietBuild && process.env.npm_config_loglevel !== 'silent' && process.argv.length > 2) { // Check if TARGET_DIR was provided as an argument
    console.log(`[WPMoo] Building scripts for: ${targetDir}`);
}

const paths = {
  src: path.join(targetDir, "resources/js"),
  dest: path.join(targetDir, "assets/js"),
  entry: path.join(targetDir, "resources/js/wpmoo.js"),
  modules: path.join(targetDir, "resources/js/wpmoo"),
};

// Helper: Create folder
const createFolderIfNotExists = (foldername) => {
  if (!fs.existsSync(foldername)) {
    fs.mkdirSync(foldername, { recursive: true });
  }
};

const MODULE_PLACEHOLDER = "/* @wpmoo-modules */";

// Banner
const year = new Date().getFullYear();
const banner = 
`/*!
 * WPMoo Framework Scripts
 * Copyright ${year} - Licensed under MIT
 */
`;

if (!fs.existsSync(paths.entry)) {
    // Silently exit if no entry file, project may not have scripts.
    process.exit(0);
}

createFolderIfNotExists(paths.dest);

try {
  let content = fs.readFileSync(paths.entry, "utf8");

  let modulesCode = "";
  if (fs.existsSync(paths.modules)) {
    const files = fs
      .readdirSync(paths.modules)
      .filter((filename) => filename.endsWith(".js"))
      .sort();

    if (files.length > 0) {
      modulesCode = files
        .map((filename) => {
          const modPath = path.join(paths.modules, filename);
          return `/* --- Module: ${filename} --- */\n` + fs.readFileSync(modPath, "utf8");
        })
        .join("\n\n");
    }
  }

  if (content.includes(MODULE_PLACEHOLDER)) {
    content = content.replace(MODULE_PLACEHOLDER, modulesCode);
  } else {
    content = modulesCode + "\n" + content;
  }

  const finalContent = banner + "\n" + content;

  const outFile = path.join(paths.dest, "wpmoo.js");
  fs.writeFileSync(outFile, finalContent);
  
} catch (error) {
  console.error(`❌ Error building scripts:`, error.message);
  process.exit(1);
}
