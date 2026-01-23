const fs = require("fs");
const path = require("path");
const ftp = require("basic-ftp");

async function main() {
  const cfg = JSON.parse(fs.readFileSync(".vscode/sftp.json", "utf8"));
  const client = new ftp.Client();
  client.ftp.verbose = false;

  const files = [
    "build/admin.js",
    "build/admin.asset.php",
    "public/admin-app.css",
    "public/admin-agent-media.js",
    "public/admin-extra-media.js",
    "bookpoint-v5.php",
    "lib/rest/settings-routes.php",
  ];

  try {
    await client.access({
      host: cfg.host,
      user: cfg.username,
      password: cfg.password,
      secure: cfg.secure,
      port: cfg.port,
      secureOptions: cfg.secureOptions,
    });

    for (const file of files) {
      const remoteBase = (cfg.remotePath || "").replace(/\/+$/, "");
      const remote = `${remoteBase}/${file.replace(/\\/g, "/")}`;
      const dir = path.posix.dirname(remote);
      await client.ensureDir(dir);
      await client.uploadFrom(file, remote);
      console.log("Uploaded", file);
    }
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
