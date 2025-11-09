import { mkdir, writeFile } from "node:fs/promises";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import lighthouse from "lighthouse";
import chromeLauncher from "chrome-launcher";

function parseArgs(rawArgs) {
  return rawArgs.reduce(
    (acc, arg) => {
      if (arg.startsWith("--url=")) {
        acc.url = arg.slice("--url=".length);
      } else if (arg.startsWith("--app=")) {
        acc.app = arg.slice("--app=".length);
      } else if (arg.startsWith("--preset=")) {
        acc.preset = arg.slice("--preset=".length);
      }
      return acc;
    },
    {
      url: "http://localhost:4173",
      app: "surfing-app",
      preset: "desktop",
    },
  );
}

async function run() {
  const { url, app, preset } = parseArgs(process.argv.slice(2));
  const chrome = await chromeLauncher.launch({
    chromeFlags: ["--headless", "--disable-gpu"],
  });

  try {
    const options = {
      logLevel: "info",
      port: chrome.port,
      output: /** @type {const} */ (["json", "html"]),
      onlyCategories: ["performance", "accessibility", "best-practices", "seo"],
      preset,
    };

    const runnerResult = await lighthouse(url, options);
    if (!runnerResult) {
      throw new Error("Lighthouse did not return a result");
    }

    const reportDir = resolve(
      dirname(fileURLToPath(import.meta.url)),
      "..",
      "reports",
      "lighthouse",
      app,
    );
    await mkdir(reportDir, { recursive: true });

    const timestamp = new Date()
      .toISOString()
      .replace(/[:.]/g, "-")
      .toLowerCase();

    const htmlReport = runnerResult.report.find((entry) =>
      entry.trim().startsWith("<!doctype html"),
    );
    const jsonReport = runnerResult.report.find((entry) => entry.startsWith("{"));

    if (htmlReport) {
      await writeFile(
        resolve(reportDir, `${timestamp}-${preset}.html`),
        htmlReport,
        "utf8",
      );
    }
    if (jsonReport) {
      await writeFile(
        resolve(reportDir, `${timestamp}-${preset}.json`),
        jsonReport,
        "utf8",
      );
    }

    const scores = Object.entries(runnerResult.lhr.categories).reduce(
      (acc, [key, value]) => {
        acc[key] = Math.round(value.score * 100);
        return acc;
      },
      /** @type {Record<string, number>} */ ({}),
    );

    // eslint-disable-next-line no-console
    console.log(
      `Lighthouse scores for ${app} (${preset}) @ ${url}:`,
      scores,
    );
  } finally {
    await chrome.kill();
  }
}

run().catch((error) => {
  // eslint-disable-next-line no-console
  console.error(error);
  process.exitCode = 1;
});


