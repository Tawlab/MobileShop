document.addEventListener('DOMContentLoaded', function() {
            const provinceSelect = document.getElementById('provinceSelect');

            fetch('../global/get_provinces.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(province => {
                        const option = document.createElement('option');
                        option.value = province.id;
                        option.textContent = province.name_th;
                        provinceSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading provinces:', error));
        });
        const districtSelect = document.getElementById('districtSelect');
        const subdistrictSelect = document.getElementById('subdistrictSelect');
        const zipcodeInput = document.getElementById('zipcodeInput');

        document.getElementById('provinceSelect').addEventListener('change', function() {
            const provinceId = this.value;

            // ล้างตัวเลือกเดิม
            districtSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';

            if (provinceId) {
                fetch(`../global/get_districts.php?province_id=${provinceId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district.id;
                            option.textContent = district.name_th;
                            districtSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading districts:', error));
            }
        });
        districtSelect.addEventListener('change', function() {
            const districtId = this.value;

            // ล้างข้อมูลเก่า
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
            zipcodeInput.value = '';

            if (districtId) {
                fetch(`../global/get_subdistricts.php?district_id=${districtId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(subdistrict => {
                            const option = document.createElement('option');
                            option.value = subdistrict.id;
                            option.textContent = subdistrict.name_th;
                            option.setAttribute('data-zipcode', subdistrict.zip_code);
                            subdistrictSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading subdistricts:', error));
            }
        });
        subdistrictSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const zipcode = selectedOption.getAttribute('data-zipcode');
            zipcodeInput.value = zipcode;
        });