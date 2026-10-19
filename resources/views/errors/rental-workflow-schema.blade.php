@extends('errors.layout')
@section('code', '503')
@section('title', 'Rental management is unavailable')
@section('message', 'The rental workflow database migration is pending. Apply database/migrations/2026_10_17_000001_extend_rental_workflows.php after backing up the database.')
