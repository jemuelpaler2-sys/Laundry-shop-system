<?php
// Ordering rules for the shop. Change the numbers here, no other file needs to change.
return [
    // Smallest load the shop accepts, in kg. Every order must be weighed.
    'min_weight' => 8.00,

    // Largest load the shop can handle, in kg.
    'max_weight' => 500.00,

    // Smallest amount an order can be charged, in pesos (8 kg x ₱20/kg).
    'min_price' => 160.00,

    // Largest amount an order can be charged, in pesos.
    'max_price' => 100000.00,

    // The only service the shop offers. Used to price orders that have no
    // service set yet.
    'default_service' => 'Wash & Fold',
];