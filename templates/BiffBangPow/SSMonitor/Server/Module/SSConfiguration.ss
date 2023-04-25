<section class="bbp-monitor-section">
    <details>
        <summary>
            <h2>$Title</h2>
        </summary>
        <div>
            <table class="bbp-monitor-results">
                <thead>
                <tr>
                    <th><%t BBPMonitoring.SSConfVariable 'Variable' %></th>
                    <th><%t BBPMonitoring.SSConfValue 'Value' %></th>
                </tr>
                </thead>
                <tbody>
                <% loop $Variables %>
                    <tr>
                        <td>$Variable</td>
                        <td>$Value</td>
                    </tr>
                <% end_loop %>
                </tbody>
            </table>
        </div>
    </details>
</section>
