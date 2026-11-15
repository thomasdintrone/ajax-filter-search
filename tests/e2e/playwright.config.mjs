import { defineConfig, devices } from '@playwright/test';

// PW_BROWSER=firefox|webkit runs the same specs in another engine (nightly). Default is Chromium.
const engine = { firefox: devices[ 'Desktop Firefox' ], webkit: devices[ 'Desktop Safari' ] }[ process.env.PW_BROWSER ] || devices[ 'Desktop Chrome' ];

// Run through bin/e2e.sh (it starts the stack and runs this inside the Playwright image so screenshots match everywhere).
export default defineConfig( {
	testDir: '.',
	testMatch: '**/*.spec.mjs',
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	outputDir: '../../.cache/e2e-results',
	snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{arg}{ext}',
	expect: { toHaveScreenshot: { maxDiffPixelRatio: 0.002, animations: 'disabled' } },
	use: {
		...engine,
		baseURL: process.env.BASE_URL || 'http://localhost:8890',
		viewport: { width: 1200, height: 900 },
		locale: 'en-US',
		timezoneId: 'UTC',
	},
} );
