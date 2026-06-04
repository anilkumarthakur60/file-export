<table>
    <thead>
        <tr><th>Name</th></tr>
    </thead>
    <tbody>
        @foreach ($names as $name)
            <tr><td>{{ $name }}</td></tr>
        @endforeach
    </tbody>
</table>
