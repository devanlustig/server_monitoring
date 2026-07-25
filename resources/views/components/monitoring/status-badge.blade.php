@props(['value', 'warning' => 70, 'danger' => 90])

@php

if($value < $warning){

    $color='success';
    $text='Healthy';

}elseif($value < $danger){

    $color='warning';
    $text='Warning';

}else{

    $color='danger';
    $text='Critical';

}

@endphp

<span class="badge bg-{{ $color }}">
    {{ $text }}
</span>