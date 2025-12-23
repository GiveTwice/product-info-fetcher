// Test helper script - echoes back the command it receives as JSON (embedded in html field)
const command = JSON.parse(process.argv[2] || '{}');

console.log(JSON.stringify({
    success: true,
    html: JSON.stringify({ _receivedCommand: command }),
    statusCode: 200,
    finalUrl: command.url || 'https://example.com',
}));
