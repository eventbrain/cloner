<?php

return [

    /**
     * Assoc. List of trait classes and their cloneable_relations.
     * Makes it easier to centrally define cloneable_relations for traits
     */
    "trait_cloneable_relations" => [
    /* Example
        BelongsToTeam::class => ["team"],
    */
    ],

    /**
     * Boolean, whether media from spatie/laravel-medialibrary should be cloned
     */
    "should_clone_media" => false,


];