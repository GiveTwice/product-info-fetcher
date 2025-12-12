<?php

function getFixture(string $path): string
{
    return file_get_contents(__DIR__.'/Fixtures/'.$path);
}
