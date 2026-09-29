// JavaScript to filter tables based on dropdown selection
document.addEventListener('DOMContentLoaded', () => {
  // Select all filter dropdowns
  const dropdowns = document.querySelectorAll('.filter-dropdown');
  dropdowns.forEach(dropdown => {
    dropdown.addEventListener('change', function () {
      const selectedFilter = this.value;
      const tableId = this.getAttribute('data-filter');
      const rows = document.querySelectorAll(`#${tableId} tbody tr`);
      rows.forEach(row => {
        const katastash = row.getAttribute('data-katastash');
        row.style.display = (selectedFilter === 'all' || katastash === selectedFilter) ? '' : 'none';
      });
    });
  });

  // Handle search for foititis (guard if element doesn't exist)
  const searchButton = document.getElementById('search-foititis-button');
  if (searchButton) {
    searchButton.addEventListener('click', () => {
      const queryInput = document.getElementById('search-foititis');
      const query = (queryInput ? queryInput.value.trim() : '');
      if (query) {
        fetch(`homepage_d.php?query=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            const resultsDiv = document.getElementById('foititis-results');
            if (!resultsDiv) return;
            resultsDiv.innerHTML = '';

            if (data.length > 0) {
              data.forEach(foititis => {
                const resultItem = document.createElement('div');
                resultItem.textContent = `${foititis.id} - ${foititis.name} ${foititis.lastname}`;
                resultItem.dataset.foititisId = foititis.id;
                resultItem.classList.add('foititis-result-item');
                resultsDiv.appendChild(resultItem);
              });

              const resultItems = document.querySelectorAll('.foititis-result-item');
              resultItems.forEach(item => {
                item.addEventListener('click', function () {
                  const form = document.getElementById('assign-thema-form');
                  if (!form) return;
                  const foititisIdInput = document.createElement('input');
                  foititisIdInput.type = 'hidden';
                  foititisIdInput.id = 'foititis-id';
                  foititisIdInput.name = 'foititis-id';
                  foititisIdInput.value = this.dataset.foititisId;
                  form.appendChild(foititisIdInput);

                  resultItems.forEach(i => i.style.display = 'none');
                  this.style.display = 'block';
                  this.classList.add('selected');
                });
              });
            } else {
              resultsDiv.textContent = 'Δεν βρέθηκαν αποτελέσματα.';
            }
          })
          .catch(error => console.error('Error fetching data:', error));
      }
    });
  }

  // Handle row click for diplwmatiki details
  const rows = document.querySelectorAll('table tbody tr');
  rows.forEach(row => {
    row.addEventListener('click', function () {
      const diplwmatikiId = this.dataset.diplwmatikiId;
      const source = this.dataset.source;
      if (!diplwmatikiId || !source) return;

      fetch(`homepage_d.php?diplwmatiki_id=${encodeURIComponent(diplwmatikiId)}&source=${encodeURIComponent(source)}`)
        .then(response => response.text())
        .then(text => {
          const data = JSON.parse(text);
          const setText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val || 'N/A';
          };
          setText('detail-title', data.diplwmatiki_title);
          setText('detail-foititis-name', data.foititis_name);
          setText('detail-foititis-lastname', data.foititis_lastname);
          setText('detail-epivlepwn-name', data.epivlepwn_name);
          setText('detail-epivlepwn-lastname', data.epivlepwn_lastname);
          setText('detail-melos1-name', data.melos1_name);
          setText('detail-melos1-lastname', data.melos1_lastname);
          setText('detail-melos2-name', data.melos2_name);
          setText('detail-melos2-lastname', data.melos2_lastname);
          setText('detail-start-date', data.start_date);
          setText('detail-end-date', data.end_date);
          setText('detail-katastash', data.katastash);
          setText('detail-bathmos', data.bathmos);
          setText('detail-link', data.link);
          setText('detail-praktiko', data.praktiko);

          const tables = document.querySelector('.diplwmatikes-tables');
          const review = document.querySelector('.anaskopisi-diplwmatikis');
          if (tables && review) {
            tables.style.display = 'none';
            review.style.display = 'block';
          }
        })
        .catch(error => console.error('Error fetching data:', error));
    });
  });

  // Back button functionality
  const backButton = document.getElementById('back-button');
  if (backButton) {
    backButton.addEventListener('click', function () {
      const tables = document.querySelector('.anaskopisi-diplwmatikis');
      const review = document.querySelector('.diplwmatikes-tables');
      if (tables && review) {
        tables.style.display = 'none';
        review.style.display = 'block';
      }
    });
  }
});

// Helpers used by the sidebar buttons
function setActive(button) {
  const buttons = document.querySelectorAll('.nav-button');
  buttons.forEach(btn => btn.classList.remove('active'));
  button.classList.add('active');
}

function loadContent(divId) {
  const divs = document.querySelectorAll('.content');
  divs.forEach(div => { div.classList.remove('visible'); div.classList.add('hidden'); });
  const selectedDiv = document.getElementById(divId);
  if (selectedDiv) { selectedDiv.classList.remove('hidden'); selectedDiv.classList.add('visible'); }
}
