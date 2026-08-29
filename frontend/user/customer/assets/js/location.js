
(function () {
    var busy = false;

    function currentScriptBaseUrl() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].getAttribute('src') || '';
            if (src.indexOf('location.js') !== -1) {
                var base = scripts[i].getAttribute('data-base-url');
                if (base) return base.replace(/\/$/, '') + '/';
            }
        }
        return '/';
    }

    var baseUrl = currentScriptBaseUrl();
    var reverseGeocodeUrl = baseUrl + 'backend/actions/reverse_geocode.php';

    function useCurrentLocation() {
        if (busy) return;

        var status = document.getElementById("locationStatus");
        status.innerText = "Getting your current location...";

        if (!navigator.geolocation) {
            status.innerText = "Location is not supported by your browser.";
            return;
        }

        busy = true;

        navigator.geolocation.getCurrentPosition(
            async function(position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;

                document.getElementById("latitude").value = lat;
                document.getElementById("longitude").value = lng;

                try {
                    var response = await fetch(
                        reverseGeocodeUrl + "?lat=" + encodeURIComponent(lat) + "&lng=" + encodeURIComponent(lng)
                    );

                    var data = await response.json();

                    if (data && data.success && data.address) {
                        document.getElementById("address").value = data.address;
                        status.innerText = "Location address detected successfully.";
                    } else {
                        status.innerText = (data && data.message) ? data.message : "Location found, but address was not detected. Please type your address manually.";
                    }
                } catch (error) {
                    status.innerText = "Unable to convert location to address. Please type your address manually.";
                }

                busy = false;
            },
            function(error) {
                if (error && error.code === 1) {
                    status.innerText = "Location permission denied. Please type your address manually.";
                } else if (error && error.code === 2) {
                    status.innerText = "Location unavailable. Please type your address manually.";
                } else if (error && error.code === 3) {
                    status.innerText = "Location detection timed out. Please type your address manually.";
                } else {
                    status.innerText = "Could not detect location. Please type your address manually.";
                }
                busy = false;
            },
            {
                enableHighAccuracy: false,
                timeout: 20000,
                maximumAge: 300000
            }
        );
    }

    var button = document.getElementById("useCurrentLocationButton");
    if (button) {
        button.addEventListener("click", useCurrentLocation);
    }
})();
