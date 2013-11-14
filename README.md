# Auto-conflict detector
----
> (c) 2013 Sortex Ltd., sortex.co.il
> github.com/sortex/git-conflict-detector

Runs as a webpage that listens to GitHub hook requests and alerts an HipChat room if a conflict has detected with other branches in the repository.

## Setup

  - Setup a webserver vhost
  - Clone files into vhost root dir
  - Generate an SSH key pair in the webserver's user dir, eg. /var/www/.ssh (See section below)
  - Add the public key you generated into the repository's GitHub "Deploy Keys"
  - Make sure `.logs` and `.cache` dirs have write permission for webserver's user
  - Edit `settings.php` to reflect your needs

## GitHub authentication

  - For GitHub authentication through `git` command-line this script requires the webserver's user having an SSH key created in `.ssh`
  - An easy way to test:
    - Temporarily change webserver's user shell from `/bin/nologin` to `/bin/bash`, if needed
    - Login as webserver's user
    - Generate key pair: `ssh-keygen -t rsa`
    - `ssh git@github.com`
    - You should see: "Hi foo! You've successfully authenticated"
    Note: Changing the webserver's user to `/bin/bash` can have certain security implications, make sure you know what you're doing

## License

Copyright (c) 2010-2013 Sortex Systems Development Ltd.

This software is provided 'as-is', without any express or implied
warranty. In no event will the authors be held liable for any damages
arising from the use of this software.

Permission is granted to anyone to use this software for any purpose,
including commercial applications, and to alter it and redistribute it
freely, subject to the following restrictions:

   1. The origin of this software must not be misrepresented; you must not
   claim that you wrote the original software. If you use this software
   in a product, an acknowledgment in the product documentation would be
   appreciated but is not required.

   2. Altered source versions must be plainly marked as such, and must not be
   misrepresented as being the original software.

   3. This notice may not be removed or altered from any source
   distribution.

