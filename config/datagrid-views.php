<?php

return [

    /*
     | Every enum implementing DefinesDatagridViewSection whose cases this
     | application's grids return from getDatagridViewSection(). The package
     | looks a view's stored section key up here to find its access rules, so
     | a grid whose section is left out (or whose key two listed enums share)
     | refuses every view request.
     */
    'sections' => [],

];
