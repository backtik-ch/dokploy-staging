@foreach ($rows as $row)
    <tr class="border-b border-gray-200 dark:border-gray-700">
        <th scope="row" class="text-left font-medium" style="padding: 0.75rem 0.5rem">
            {{ $row['name'] }}
            @if ($row['dev']) <span class="text-xs text-gray-500">dev</span> @endif
            @if ($row['manager']) <span class="text-xs text-gray-500">{{ $row['manager'] }}</span> @endif
            @if ($row['path']) <p class="text-xs text-gray-500 break-all">{{ $row['path'] }}</p> @endif
        </th>
        <td style="padding: 0.75rem 0.5rem">{{ $row['required'] }}</td>
        <td style="padding: 0.75rem 0.5rem">{{ $row['locked'] }}</td>
        <td style="padding: 0.75rem 0.5rem">{{ $row['installed'] }}</td>
        <td style="padding: 0.75rem 0.5rem">{{ $row['production'] }}</td>
        <td style="padding: 0.75rem 0.5rem">
            <p>Image : {{ $row['build_status'] }}</p>
            <p class="text-gray-500 dark:text-gray-400">Production : {{ $row['production_status'] }}</p>
        </td>
    </tr>
@endforeach
