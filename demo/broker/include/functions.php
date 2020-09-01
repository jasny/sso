<?php

declare(strict_types=1);

/**
 * Redirect and through specified URL
 */
function redirect(string $url): void
{
    header("Location: $url", true, 303);
    echo "You're redirected to <a href='$url'>$url</a>";
}
