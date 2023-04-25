<section class="bbp-monitor-section">
    <details>
        <summary>
            <h2>$Title</h2>
        </summary>

        <div>
            <table class="bbp-monitor-results">
                <thead>
                <tr>
                    <th><%t BBPMonitoring.SystemVariable 'Variable' %></th>
                    <th><%t BBPMonitoring.SystemValue 'Value' %></th>
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

            <% if $Environment %>
                <h2><%t BBPMonitoring.EnvVariables 'Environment Variables' %></h2>
                <table class="bbp-monitor-results">
                    <thead>
                    <tr>
                        <th><%t BBPMonitoring.EnvVariable 'Variable' %></th>
                        <th><%t BBPMonitoring.EnvValue 'Value' %></th>
                    </tr>
                    </thead>
                    <tbody>
                    <% loop $Environment %>
                        <tr>
                            <td>$Variable</td>
                            <td>$Value</td>
                        </tr>
                    <% end_loop %>
                    </tbody>
                </table>
            <% end_if %>
        </div>
    </details>
</section>
