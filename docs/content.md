# wiki.md content format

## Content example

Each page is essentially a YFM & Markdown file that keeps its own history as base64 encoded, gzipped, unified diffs. Here's an example:

```
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
