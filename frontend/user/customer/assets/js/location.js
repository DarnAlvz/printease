
function useCurrentLocation() {
    const status = document.getElementById("locationStatus");
    status.innerText = "Getting your current location...";

    if (!navigator.geolocation) {
        status.innerText = "Location is not supported by your browser.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lng;

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`
                );

                const data = await response.json();

                if (data && data.display_name) {
                    document.getElementById("address").value = data.display_name;
                    status.innerText = "Location address detected successfully.";
                } else {
                    status.innerText = "Location found, but address was not detected. Please type your address manually.";
                }
            } catch (error) {
                status.innerText = "Unable to convert location to address. Please type your address manually.";
            }
        },
        function() {
            status.innerText = "Location permission denied. Please type your address manually.";
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
