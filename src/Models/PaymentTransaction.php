<?php
namespace Gokulsingh\LaravelPayhub\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentTransaction extends Model{ protected $fillable=['gateway','type','transaction_id','amount','currency','payload','status']; protected $casts=['payload'=>'array']; }

