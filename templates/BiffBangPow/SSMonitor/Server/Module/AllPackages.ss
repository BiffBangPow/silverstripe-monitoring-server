<section class="bbp-monitor-section">
    <details>
        <summary>
            <h2>$Title</h2>
        </summary>

        <div>
            <table class="bbp-monitor-results">
                <thead>
                <tr>
                    <th><%t BBPMonitoring.Packagename 'Package' %></th>
                    <th><%t BBPMonitoring.PackageVersion 'Version' %></th>
                </tr>
                </thead>
                <tbody>
                <% loop $Packages %>
                    <tr>
                        <td>$PackageName</td>
                        <td>$PackageVersion</td>
                    </tr>
                <% end_loop %>
                </tbody>
            </table>
        </div>
    </details>
</section>
