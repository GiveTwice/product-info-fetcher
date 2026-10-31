const { test } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { spawn } = require('node:child_process');
const path = require('node:path');

function fetch(url, timeout) {
    return new Promise((resolve, reject) => {
        const child = spawn(process.execPath, [path.resolve(__dirname, '../../bin/fetch-html.cjs'), JSON.stringify({
            url, timeout, chromePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
        })]);
        let stdout = '';
        child.stdout.on('data', data => stdout += data);
        child.on('error', reject);
        child.on('close', code => {
            try { resolve({ code, result: JSON.parse(stdout) }); } catch (error) { reject(error); }
        });
    });
}

test('retains DOM, main response status and final URL on navigation timeout', async () => {
    const server = http.createServer((req, res) => {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.write('<!doctype html><meta charset="utf-8"><h1>Product already available</h1>');
        // Deliberately leave the document open until the browser times out.
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        const url = `http://127.0.0.1:${server.address().port}/product`;
        const { code, result } = await fetch(url, 750);
        assert.equal(code, 0);
        assert.equal(result.timedOut, true);
        assert.equal(result.statusCode, 200);
        assert.equal(result.finalUrl, url);
        assert.match(result.html, /Product already available/);
    } finally {
        server.closeAllConnections();
        server.close();
    }
});

test('returns diagnostics for blocked pages and closes Chrome cleanly', async () => {
    const server = http.createServer((req, res) => {
        res.writeHead(403, { 'Content-Type': 'text/html' });
        res.end('<title>Access denied</title>');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        const { code, result } = await fetch(`http://127.0.0.1:${server.address().port}/blocked`, 5000);
        assert.equal(code, 1);
        assert.equal(result.statusCode, 403);
        assert.match(result.html, /Access denied/);
    } finally {
        server.closeAllConnections();
        server.close();
    }
});
