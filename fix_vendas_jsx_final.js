const fs = require('fs');

const filePath = 'resources/js/pages/Backend/Ventas/Index.tsx';
const content = fs.readFileSync(filePath, 'utf8');

// Fix the JSX syntax error by properly separating the closing div tags
let fixedContent = content;

// Find the problematic pattern and fix it
// The issue is around line 2028 where we have "</div>                                                            {(prod as any)..."

// Use a regex to replace the problematic pattern
fixedContent = fixedContent.replace(
    /<\/div>\s*\n\s*{\(prod as any\)\?\.envase_retornable === true \&\& \(/g,
    '</div>\n    {(prod as any)?.envase_retornable === true && ('
);

// Also fix similar patterns
fixedContent = fixedContent.replace(
    /<\/div>\\s*\n\\s*{\(prod as any\)\?/g,
    '</div>\n    {'
);

// Write back
fs.writeFileSync(filePath, fixedContent, 'utf8');

console.log("Fixed JSX syntax error in Ventas/Index.tsx");
