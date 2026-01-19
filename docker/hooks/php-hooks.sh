#!/bin/bash
# docker/hooks/php-hooks.sh
# Batch PHP operations for Git hooks
#
# Usage:
#   ./docker/hooks/php-hooks.sh lint file1.php file2.php ...
#   ./docker/hooks/php-hooks.sh fix file1.php file2.php ...
#
# Commands:
#   lint  - Check PHP syntax (php -l) for all files
#   fix   - Fix coding style (php-cs-fixer) and re-stage files
#
# Uses hook-runner.sh to determine exec vs run.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Show usage
usage() {
    echo "Usage: $0 <command> [files...]"
    echo ""
    echo "Commands:"
    echo "  lint  - Check PHP syntax"
    echo "  fix   - Fix coding style and re-stage"
    exit 1
}

# Require at least command argument
[[ $# -lt 1 ]] && usage

COMMAND="$1"
shift
FILES=("$@")

# Exit early if no files
[[ ${#FILES[@]} -eq 0 ]] && exit 0

# Convert host paths to container paths
CONTAINER_PATHS=()
for file in "${FILES[@]}"; do
    CONTAINER_PATHS+=("/var/www/html/$file")
done

case "$COMMAND" in
    lint)
        # Check PHP syntax for all files in single container
        INNER_SCRIPT='
exit_code=0
for file in "$@"; do
    if ! php -l "$file"; then
        exit_code=1
    fi
done
exit $exit_code
'
        "$SCRIPT_DIR/hook-runner.sh" php sh -c "$INNER_SCRIPT" -- "${CONTAINER_PATHS[@]}"
        ;;

    fix)
        # Fix coding style with php-cs-fixer
        "$SCRIPT_DIR/hook-runner.sh" php vendor/bin/php-cs-fixer fix \
            --config=.php-cs-fixer.dist.php \
            "${CONTAINER_PATHS[@]}"

        # Re-stage fixed files
        git add "${FILES[@]}"
        ;;

    *)
        echo "Unknown command: $COMMAND"
        usage
        ;;
esac
