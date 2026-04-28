import os

input_file = "/Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/modules/bookshelf/modules/reading/assets/js/my-book-reconstructed.js"
output_dir = "/Users/nicolaspavez/Local Sites/nupoliteia/app/public/wp-content/plugins/politeia-learning/modules/bookshelf/modules/reading/assets/js/my-book/"

with open(input_file, 'r') as f:
    lines = f.readlines()

# Clean up any previously broken splits (like the un-indentation I did)
# Actually, it's better to just start from a clean state.

# I'll find the IIFE again and remove it first.
start_iife = -1
end_iife = -1
for i, line in enumerate(lines):
    if "(function () {" in line: start_iife = i
    if "})();" in line: end_iife = i

content_lines = []
if start_iife != -1 and end_iife != -1:
    for i in range(start_iife + 1, end_iife):
        l = lines[i]
        if l.startswith("  "): l = l[2:]
        if l.startswith("let "): l = "var " + l[4:]
        if l.startswith("const "): l = "var " + l[6:]
        content_lines.append(l)
else:
    content_lines = lines

# Now split by balance of braces
parts = []
current_part = []
brace_balance = 0

for line in content_lines:
    current_part.append(line)
    brace_balance += line.count("{")
    brace_balance -= line.count("}")
    
    if len(current_part) >= 450 and brace_balance == 0:
        parts.append(current_part)
        current_part = []

if current_part:
    parts.append(current_part)

# Write parts
for i, part in enumerate(parts):
    filename = os.path.join(output_dir, f"part-{i:02d}.js")
    with open(filename, 'w') as f:
        f.writelines(part)
    print(f"Wrote {filename} ({len(part)} lines)")
