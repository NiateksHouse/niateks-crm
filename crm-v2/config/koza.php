<?php

return ['catalog' => json_decode(file_get_contents(__DIR__.'/koza-catalog.json'), true, 512, JSON_THROW_ON_ERROR)];
