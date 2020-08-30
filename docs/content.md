# wiki.md content format

## Filesystem

Every page in wiki.md is a Markdown file (`*.md`) in the content directory on the server. Every URL that does not end in a slash (`/`) maps to a file of the same name:

```
https://wiki.example.org/about        # /about.md
https://wiki.example.org/animal/lion  # /animal/lion.md
https://wiki.example.org/plant/rose   # /plant/rose.md
```

Even folders are pages that are automatically mapped to a `README.md`:

```
https://wiki.example.org/             # /README.md
https://wiki.example.org/animal/      # /animal/README.md
https://wiki.example.org/plant/       # /plant/README.md
```

You can find these `*.md` files in the `data/content/` folder of your wiki.md installation.

## Content example

Each `*.md` file is a YFM & [Markdown](https://en.wikipedia.org/wiki/Markdown) file that keeps its own history as base64 encoded, gzipped, [unified diffs](https://en.wikipedia.org/wiki/Diff_utility#Unified_format). Here's an example:

```md
---
author: Nerdreich
date: 2020-04-12T19:09:52+00:00
title: A true welcome!
diff:
  - >-
    H4sIAO6Rk14CA43LsQ6CMBSF4Vme4o5KbblQwIaJERPfwDg04QqE0jaAQd9eiI4Ojifn/zjn0JIx
    Tgz1LsEEOaY8loBYYFbIVMRSJhmmpxzY+mLAGPuA5IfIizgXCpWSmYrVV5QlcAlMQlkGvNroEc6g
    B9AW6KkHbwjCQY997RYbgtcNiYD9GW6BmRzcSc+PkUDD9dLZ/rZv59lPRRSRFUvXd57qTgs3NtG2
    ourlaTRreADrFhG8AQeEtnwHAQAA
  - >-
    H4sIADiSk14CA22NT4vCMBBHz9tP8dtzTJj+WZv2VNCzpwXPUcdtMW2kqYKQD2+7VkTwMTCHN4+R
    UqJma12i2sNXQglJymScgqikZRkvlSat0x8da4jRUiSEeBTph0KXlKi8KDRleVbMRVVBpguCyBY5
    qioSkQgb0zIQ1uz3fXMeGtfhSRi1/Gde70y6dd2Jb2HLsM2JMdTOs3rVh978uS6snLPsB7jjeMEt
    jLUqTN9/68ZjHIMr9zdcPB8vFoPZWf6O7kyIlDMSAQAA
---
# Hello World

Hello, I am an example *Markdown* page. I also feature a [Link](https://en.wikipedia.org/wiki/Hyperlink) now.

|Animal|Description         |
|------|--------------------|
|monkey|We like those.      |
|dragon|Coolest of them all.|

This is a very useful table!
```
