# OregonFlora Deploy Pipeline
This is the customized CI/CD pipeline for OregonFlora. Whenever there is a push to the Github repo, it will automatically build the OregonFlora and:
- Save deployment result to a log
- Send a message to Slack channel

## Installation
> Move `apache-site/autodeploy.conf` to `sites-available` of the Apache configuration folder. Adjust the conf as appropriated, then enable the site. 

## How it works
1. GitHub webhook sends a POST request to this script whenever you push to the monitored branch
2. The script verifies the request is authentic by checking the HMAC-SHA256 signature in the `X-Hub-Signature-256` header against the shared SECRET
3. It filters for 'push' events only, then checks if the branch matches the configured BRANCH
4. If the branch matches, it executes the DEPLOY_CMD (typically `./run.sh`)
5. The script logs the deployment exit code and output
6. If deployment succeeds, it sends a notification to the configured Slack channel with commit details
7. Returns a 200 OK response with deployment status

## Environment variable
- SECRET: the secret code shared between this pipeline and the Github Webhook event
- BRANCH=refs/heads/<name of the branch to monitor>
- LOG_FOLDER: leave empty to save log close to the build script
- DEPLOY_CMD: the command run when the system receives the POST from Github. For some reason, Apache only allows executable *without* argument, so 2 scripts `run.sh` and `build.sh` were created to combat this. Write `'./run.sh'` in, then change ACCOUNT in `run.sh` to whatever user you want to use to deploy the code.
- BUILD_DIR: the directory that stores the git repo locally to pull new and deploy.
- SLACK_WEBHOOK_URL: self-explanatory. You need to create an app and a channel to hook this app to to have this URL.

## Tech Stack
- PHP
- Bash
- Slack (webhook, channel)
