---
layout: layouts/base.njk
permalink: /command-line/installation/
title: Installation
summary: There is no release to download yet — the tool is run from a checkout, and a PHAR is one command away.
section: The command line
order: 0
---

`mdoffice` is the whole suite at a terminal. This page is how to get it; [Basic
use](/command-line/basic/) is how to run it.

## Getting it

There is no release to download yet: the repository has no tag, the releases page is empty, and
there is no Homebrew or winget package. The tool is run from a checkout, and the checkout needs
its two siblings beside it, because the manifest resolves both libraries through `path`
repositories pointing at `../markdown-word` and `../markdown-presentation`.

```bash
git clone https://github.com/markdown-office/mdoffice.git
git clone https://github.com/markdown-office/markdown-word.git
git clone https://github.com/markdown-office/markdown-presentation.git
cd mdoffice && composer install

php bin/mdoffice word notes.md
```

A PHAR is one command away, and it carries the two libraries inside it. The build rewrites the
two `path` repositories as `vcs` and resolves the libraries from GitHub, so the archive is one
file that needs nothing beside it:

```bash
composer build:phar          # build/mdoffice.phar
php build/mdoffice.phar word notes.md
```

