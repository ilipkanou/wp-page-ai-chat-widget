// Unit test for the name fallback (extractFirstName), read straight from a frontend snippet file.
// The fallback runs only when the AI name call fails; it must find "Bob" in "Hello. My name is Bob."
// Usage: node tests/test-name-fallback.js <frontend-snippet.php>
const fs = require('fs');
const vm = require('vm');

const src = fs.readFileSync(process.argv[2], 'utf8');
const start = src.indexOf('    const NOT_NAMES = new Set(');
const end = src.indexOf('    const App = () => {');
if (start < 0 || end < 0) { console.log('FAIL  could not find extractFirstName in the file'); process.exit(1); }
const ctx = {};
vm.runInNewContext(src.slice(start, end) + '\nthis.extractFirstName = extractFirstName;', ctx);

const cases = [
  ['Hello. My name is Bob.', 'Bob'], ['hi im sarah', 'Sarah'], ["It's Dr. Robert Johnson here", 'Robert'],
  ['Mike', 'Mike'], ['What does the book cost?', ''], ["I'm interested in the book", ''], ['Tim', 'Tim'],
  ['Hi, Bob Smith', 'Bob'], ['hello', ''], ['BOB', 'Bob'], ['my name is McKenzie', 'McKenzie'],
  ['It’s Anna', 'Anna'], ['This is Bob from ACME', 'Bob'], ['Call me Ishmael.', 'Ishmael'],
  ['Игорь', 'Игорь'], ['i am from Toronto', ''], ['Hey there', ''], ['It is me', ''],
  ['Good morning, Jane here', 'Jane'], ['Zoë', 'Zoë'],
];
let fail = 0;
for (const [input, want] of cases) {
  const got = ctx.extractFirstName(input);
  if (got !== want) { fail++; console.log(`FAIL  ${JSON.stringify(input)} -> ${JSON.stringify(got)} (want ${JSON.stringify(want)})`); }
}
console.log(`name fallback: ${cases.length - fail} pass, ${fail} fail`);
process.exit(fail ? 1 : 0);
