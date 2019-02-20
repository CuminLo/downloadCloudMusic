<?php

$ar1 = [
  [
  'name' => 1,
  ],
  [
  'name' => 2,
  ]
];

$ar2 = [
  [
  'name' => 3,
  ],
  [
  'name' => 4,
  ]
];

$ar = array_merge($ar1, $ar2);

var_dump($ar);
