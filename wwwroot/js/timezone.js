// Display timezone at the top right
document.addEventListener('DOMContentLoaded', function() {
    var timezoneDisplay = document.querySelector('.timezone-display');
    if (timezoneDisplay) {
        var timezoneName = Intl.DateTimeFormat().resolvedOptions().timeZone;

        // Create timezone to abbreviation mapping
        var tzMap = {
            // US/Canada
            'America/New_York': { std: 'EST', dst: 'EDT' },
            'America/Chicago': { std: 'CST', dst: 'CDT' },
            'America/Denver': { std: 'MST', dst: 'MDT' },
            'America/Los_Angeles': { std: 'PST', dst: 'PDT' },
            'America/Anchorage': { std: 'AKST', dst: 'AKDT' },
            'Pacific/Honolulu': { std: 'HST', dst: 'HST' },
            'America/Phoenix': { std: 'MST', dst: 'MST' },
            // Australia
            'Australia/Sydney': { std: 'AEST', dst: 'AEDT' },
            'Australia/Melbourne': { std: 'AEST', dst: 'AEDT' },
            'Australia/Adelaide': { std: 'ACST', dst: 'ACDT' },
            'Australia/Brisbane': { std: 'AEST', dst: 'AEST' },
            'Australia/Darwin': { std: 'ACST', dst: 'ACST' },
            'Australia/Perth': { std: 'AWST', dst: 'AWST' },
            'Australia/Hobart': { std: 'AEST', dst: 'AEDT' },
            // Europe
            'Europe/London': { std: 'GMT', dst: 'BST' },
            'Europe/Paris': { std: 'CET', dst: 'CEST' },
            'Europe/Berlin': { std: 'CET', dst: 'CEST' },
            'Europe/Rome': { std: 'CET', dst: 'CEST' },
            'Europe/Madrid': { std: 'CET', dst: 'CEST' },
            'Europe/Amsterdam': { std: 'CET', dst: 'CEST' },
            'Europe/Brussels': { std: 'CET', dst: 'CEST' },
            'Europe/Zurich': { std: 'CET', dst: 'CEST' },
            // Asia
            'Asia/Tokyo': { std: 'JST', dst: 'JST' },
            'Asia/Shanghai': { std: 'CST', dst: 'CST' },
            'Asia/Hong_Kong': { std: 'HKT', dst: 'HKT' },
            'Asia/Singapore': { std: 'SGT', dst: 'SGT' },
            'Asia/Seoul': { std: 'KST', dst: 'KST' },
            'Asia/Dubai': { std: 'GST', dst: 'GST' },
            'Asia/Kolkata': { std: 'IST', dst: 'IST' },
            'Asia/Bangkok': { std: 'ICT', dst: 'ICT' },
            // Others
            'Pacific/Auckland': { std: 'NZST', dst: 'NZDT' },
            'America/Toronto': { std: 'EST', dst: 'EDT' },
            'America/Vancouver': { std: 'PST', dst: 'PDT' },
        };

        // Get abbreviation from map or try to derive from date
        var tzInfo = tzMap[timezoneName];
        var abbreviation = '';

        if (tzInfo) {
            // Simple approach: always default to dst in Nov/Dec in AU
            var now = new Date();
            var month = now.getMonth();

            // For Australian timezones, DST typically Oct-Apr (months 9-3)
            var isAustralian = timezoneName.indexOf('Australia/') === 0;
            var isDST = false;

            if (isAustralian) {
                isDST = month <= 3 || month >= 9;
            } else {
                // For Northern hemisphere, DST typically Mar-Nov (months 2-10)
                isDST = month >= 2 && month <= 9;
            }

            abbreviation = isDST ? tzInfo.dst : tzInfo.std;
        } else {
            // Fallback: try to get from date string
            try {
                var formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone: timezoneName,
                    timeZoneName: 'short',
                    month: 'numeric',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric'
                });
                var parts = formatter.formatToParts(new Date());
                var tzPart = parts.find(part => part.type === 'timeZoneName');
                if (tzPart) {
                    abbreviation = tzPart.value.replace(/^GMT[+\-]\d+:\d+$/, 'GMT');
                }
            } catch (e) {
                // Ignore errors
            }
        }

        timezoneDisplay.textContent = abbreviation || 'TZ';
    }
});
