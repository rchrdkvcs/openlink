#!/usr/bin/env node

import { appendFileSync } from 'node:fs';
import semanticRelease from 'semantic-release';

const options = {};

if (process.argv.includes('--dry-run')) {
    options.dryRun = true;
}

if (process.argv.includes('--no-ci')) {
    options.ci = false;
}

function setOutput(name, value) {
    if (!process.env.GITHUB_OUTPUT) {
        return;
    }

    appendFileSync(process.env.GITHUB_OUTPUT, `${name}=${value}\n`);
}

try {
    const result = await semanticRelease(options);

    if (!result) {
        console.log('No release published.');
        setOutput('released', 'false');
        process.exit(0);
    }

    const version = result.nextRelease.version;

    console.log(`Published release ${version}.`);
    setOutput('released', 'true');
    setOutput('version', version);
} catch (error) {
    console.error(error);
    process.exit(1);
}
