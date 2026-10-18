<?php

return [
    'images' => json_decode(file_get_contents(resource_path('data/marketing-images.json')), true, 512, JSON_THROW_ON_ERROR),
];
