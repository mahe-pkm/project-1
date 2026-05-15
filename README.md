# Generic Website Agent + Skill Pack

This pack is designed for using Antigravity in an agentic workflow for any website project.

## Main Files

```txt
AGENTS.md
SKILL.md
```

Place both files in your project root.

## Included Extras

```txt
docs/deployment-update-flow.md
docs/asset-management.md
docs/qa-checklist.md
deploy/update.php
assets/asset-map.json
assets/js/asset-loader.js
prompts/
.gitignore
```

## Recommended Usage

```txt
1. Put AGENTS.md and SKILL.md in the project root.
2. Ask Antigravity to read both files before starting.
3. Give one page or section at a time.
4. Use screenshot/reference if available.
5. Ask for analysis first.
6. Then build.
7. Then run tester skill.
8. Sync/push to Git.
9. Use deploy/update.php only after configuring and testing it.
```

## Generic Build Prompt

```txt
Read AGENTS.md and SKILL.md first.

Build a responsive website/page for:
[describe website/page]

First:
1. Analyze the requirement.
2. Create a plan.
3. List files to create/change.
4. Then build semantic HTML/CSS/JS.
5. Use asset-map.json for assets.
6. Run QA checklist.
7. Summarize changed files.
```

## Deployment Warning

`deploy/update.php` is a template. Before production:

```txt
1. Change WEBHOOK_SECRET.
2. Change REPO_NAME.
3. Change REPO_ZIP_URL.
4. Test on staging.
5. Confirm backups work.
6. Confirm wrong secret returns 403.
```
