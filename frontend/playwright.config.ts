import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./e2e",
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? "github" : "list",
    outputDir: "./test-results",
    use: {
        baseURL: process.env.E2E_BASE_URL ?? "http://127.0.0.1:8081",
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
        ...devices["Desktop Chrome"],
    },
});
