const path = require("path");
const fs = require("fs");

// 1. Determine the Project Root (Target)
const targetDir = process.argv[2] ? path.resolve(process.argv[2]) : process.cwd();

console.log(`[WPMoo] Target Directory: ${targetDir}`);

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

console.log(`[WPMoo] Building scripts...`);

if (!fs.existsSync(paths.entry)) {
  console.error(`❌ Error: Entry file not found at ${paths.entry}`);
  process.exit(1);
}

createFolderIfNotExists(paths.dest);

try {
  // 1. Read Entry File
  let content = fs.readFileSync(paths.entry, "utf8");

  // 2. Read Modules
  let modulesCode = "";
  if (fs.existsSync(paths.modules)) {
    const files = fs
      .readdirSync(paths.modules)
      .filter((filename) => filename.endsWith(".js"))
      .sort(); // Sort to ensure deterministic order

    if (files.length > 0) {
      console.log(`[WPMoo] Found ${files.length} modules to inject.`);
      modulesCode = files
        .map((filename) => {
          const modPath = path.join(paths.modules, filename);
          return `/* --- Module: ${filename} --- */\n` + fs.readFileSync(modPath, "utf8");
        })
        .join("\n\n");
    }
  }

  // 3. Inject Modules
  if (content.includes(MODULE_PLACEHOLDER)) {
    content = content.replace(MODULE_PLACEHOLDER, modulesCode);
  } else {
    // Append if placeholder missing (fallback)
    content = modulesCode + "\n" + content;
  }

  // 4. Add Banner
  const finalContent = banner + "\n" + content;

  // 5. Write Output
  const outFile = path.join(paths.dest, "wpmoo.js");
  fs.writeFileSync(outFile, finalContent);
  
  console.log(`[WPMoo] Scripts built successfully! 📜`);
  console.log(`[WPMoo] Output: ${outFile}`);

} catch (error) {
  console.error(`❌ Error building scripts:`, error.message);
  process.exit(1);
}
