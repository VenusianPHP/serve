#!/bin/sh
# A sendmail stand-in for the mail tests: writes the message on stdin to its own file in the mailbox directory named first.
cat > "$(mktemp "$1/message.XXXXXX")"
