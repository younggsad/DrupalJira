import { rm, writeFile } from 'node:fs/promises';

// Preserve standard HTML reporting, but keep no report after a successful run.
export default class FailureHtmlReporter {
  onEnd(result) { this.passed = result.status === 'passed'; }
  async onExit() {
    if (this.passed) {
      await rm(`.playwright/reports/${process.env.E2E_ARTIFACT_ID}`, { recursive: true, force: true });
    } else {
      await writeFile('.playwright/latest-report.txt', process.env.E2E_ARTIFACT_ID);
    }
  }
}
