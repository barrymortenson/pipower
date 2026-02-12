<x-mail::message>
# {{ $eventSubject }}

**Device:** {{ $hostname }}

{{ $message }}

@if($eventType === 'power_lost')
The UPS is now running on battery power. If power is not restored soon, the system may shut down when the battery is depleted.
@elseif($eventType === 'power_restored')
AC power has been restored and the UPS is now charging.
@elseif($eventType === 'battery_low')
The UPS battery is critically low and the system may shut down soon if power is not restored.
@elseif($eventType === 'ups_disconnected')
Unable to communicate with the UPS. Please check the USB connection.
@elseif($eventType === 'ups_reconnected')
Communication with the UPS has been restored.
@endif

</x-mail::message>
