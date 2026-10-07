<?php

namespace App;

enum UserRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case SALES = 'sales';
}
