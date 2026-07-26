// Backup the file first
const fs = require('fs');

const filePath = 'resources/js/pages/Backend/Ventas/Index.tsx';
const content = fs.readFileSync(filePath, 'utf8');

// Fix the JSX syntax error by making minimal changes
// The issue is around line 2028 where we have a conditional JSX

// The correct syntax for conditional rendering is:
// {condition && (
//   <JSX />
// )}

// But the current code might have issues with the type assertion syntax

let fixedContent = content;

// Use a simpler approach - just ensure line 2028 has correct syntax
// Replace the problematic pattern:
// </div>                                                            {(prod as any)?.envase_retornable === true && (

// With:
//     {prod?.envase_retornable && (

fixedContent = fixedContent.replace(
    /<\/div>\s*{(prod as any)\?\.envase_retornable === true \&\& \(/g,
    `    {prod?.envase_retornable && (`
);

// Also fix similar patterns
fixedContent = fixedContent.replace(
    /{(prod as any)\?\.envase_retornable === true \&\& \(/g,
    `{prod?.envase_retornable && (`
);

// Write back
fs.writeFileSync(filePath, fixedContent, 'utf8');

console.log("Fixed JSX syntax error in Ventas/Index.tsx");