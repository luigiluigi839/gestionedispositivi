// File: JS/gestione_spostamenti.js
document.addEventListener('DOMContentLoaded', function() {
    // Riferimenti agli elementi del DOM
    const tableBody = document.getElementById('tableBody');
    const searchInput = document.getElementById('searchInput');
    const marcaFilter = document.getElementById('marca-filter');
    const modelloFilter = document.getElementById('modello-filter');
    const noloFilter = document.getElementById('nolo-filter');
    const assistenzaFilter = document.getElementById('assistenza-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const headers = document.querySelectorAll('th[data-sortable]');
    const tableContainer = document.querySelector('.table-container');

    // Mappa per i filtri
    const filterMap = {
        'marca-filter': 'Marca',
        'modello-filter': 'Modello',
        'nolo-filter': 'Nolo_Cash',
        'assistenza-filter': 'Assistenza'
    };
    const allSelects = [marcaFilter, modelloFilter, noloFilter, assistenzaFilter];
    
    // Variabile globale per contenere i dati una volta caricati
    let groupedData = {};

    /**
     * Mostra un messaggio di caricamento o di errore nella tabella.
     * @param {string} message - Il messaggio da visualizzare.
     * @param {boolean} isError - Se il messaggio è un errore.
     */
    function showTableMessage(message, isError = false) {
        tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; color:${isError ? 'red' : 'black'};">${message}</td></tr>`;
    }

    /**
     * Popola la tabella con i dati forniti.
     * @param {object} data - L'oggetto contenente i dati degli spostamenti.
     */
    function renderTable(data) {
        tableBody.innerHTML = ''; // Pulisce la tabella
        if (Object.keys(data).length === 0) {
            showTableMessage("Nessun record trovato.");
            return;
        }

        const userPerms = {
            canEdit: tableContainer.dataset.canEdit === '1',
            canDelete: tableContainer.dataset.canDelete === '1'
        };

        let content = '';
        for (const [group_key, group] of Object.entries(data)) {
            const main = group.main;
            const is_bundle = group.accessori.length > 0;

            const dataRitiro = main.Data_Ritiro ? new Date(main.Data_Ritiro).toLocaleDateString('it-IT') : '-';
            const dataInstall = new Date(main.Data_Install).toLocaleDateString('it-IT');

            let actionButtons = `<a href="visualizza_spostamento.php?id=${main.ID}" class="btn btn-visualizza" title="Visualizza">👁️</a>`;
            if (userPerms.canEdit) {
                actionButtons += ` <a href="modifica_spostamenti.php?id=${main.ID}" class="btn btn-modifica" title="Modifica">✏️</a>`;
            }
            if (userPerms.canDelete) {
                const confirmMessage = is_bundle
                    ? "ATTENZIONE: Stai eliminando lo spostamento di un bundle. Verranno eliminati anche gli spostamenti di tutti gli altri componenti per questa installazione. Continuare?"
                    : "Sei sicuro di voler eliminare questo record di spostamento?";
                actionButtons += ` <a href="../PHP/elimina_spostamento.php?id=${main.ID}" class="btn btn-elimina" onclick="return confirm('${confirmMessage.replace(/'/g, "\\'")}')" title="Elimina">🗑️</a>`;
            }

            content += `
                <tr class="${is_bundle ? 'bundle-row' : ''}" data-group-key="${group_key}">
                    <td><strong>${is_bundle ? '<span class="toggle-icon">►</span>' : ''}${main.Seriale || main.Dispositivo || 'N/D'}</strong></td>
                    <td><strong>${main.Marca || 'N/D'}</strong></td>
                    <td><strong>${main.Modello || 'N/D'}</strong></td>
                    <td><strong>${main.Azienda}</strong></td>
                    <td><strong>${dataInstall}</strong></td>
                    <td><strong>${dataRitiro}</strong></td>
                    <td>${main.Nolo_Cash || '-'}</td>
                    <td>${main.Assistenza || '-'}</td>
                    <td class="action-buttons">${actionButtons}</td>
                </tr>
            `;

            for (const accessory of group.accessori) {
                 content += `
                    <tr class="accessory-row" data-group-key="${group_key}" style="display: none;">
                        <td>${accessory.Seriale || accessory.Dispositivo || 'N/D'}</td>
                        <td>${accessory.Marca || 'N/D'}</td>
                        <td>${accessory.Modello || 'N/D'}</td>
                        <td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                `;
            }
        }
        tableBody.innerHTML = content;
    }

    /**
     * Funzioni di filtraggio e UI (invariate dalla versione precedente, ma ora operano sulla variabile `groupedData`)
     */
    function getUniqueValues(data, key) {
        const values = new Set();
        for (const groupKey in data) {
            const group = data[groupKey];
            if (group.main && group.main[key]) values.add(group.main[key]);
            group.accessori.forEach(acc => {
                if (acc && acc[key]) values.add(acc[key]);
            });
        }
        return [...values].sort();
    }
    
    function populateSelect(selectElement, options, selectedValue) {
        const currentVal = selectedValue;
        const defaultText = selectElement.id === 'marca-filter' ? 'Tutte' : 'Tutti';
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        options.forEach(value => {
            const option = new Option(value, value);
            if (value === currentVal) option.selected = true;
            selectElement.add(option);
        });
    }

    function updateUI() {
        const currentFilters = {
            search: searchInput.value.toUpperCase(),
            'marca-filter': marcaFilter.value,
            'modello-filter': modelloFilter.value,
            'nolo-filter': noloFilter.value,
            'assistenza-filter': assistenzaFilter.value,
        };
        
        const getFilteredData = (data, filters, excludeKey = null) => {
            let filteredGroups = {};
            for (const [groupKey, group] of Object.entries(data)) {
                const allDevicesInGroup = [group.main, ...group.accessori];
                let groupMatches = false;
                for (const deviceData of allDevicesInGroup) {
                    if (!deviceData) continue;
                    let deviceMatchesAllFilters = true;

                    if (filters.search && ![deviceData.Seriale, deviceData.Seriale_Inrete, deviceData.Azienda].join(' ').toUpperCase().includes(filters.search)) {
                        deviceMatchesAllFilters = false;
                    }
                    
                    for (const select of allSelects) {
                        if (select.id === excludeKey) continue;
                        const filterValue = filters[select.id];
                        const dataKey = filterMap[select.id];
                        if (filterValue && deviceData[dataKey] !== filterValue) {
                            deviceMatchesAllFilters = false;
                            break;
                        }
                    }
                    
                    if (deviceMatchesAllFilters) {
                        groupMatches = true;
                        break; 
                    }
                }
                if (groupMatches) filteredGroups[groupKey] = group;
            }
            return filteredGroups;
        };
        
        allSelects.forEach(select => {
            const dataKey = filterMap[select.id];
            const dataForThisSelect = getFilteredData(groupedData, currentFilters, select.id);
            const options = getUniqueValues(dataForThisSelect, dataKey);
            populateSelect(select, options, select.value);
        });
        
        const finalVisibleData = getFilteredData(groupedData, currentFilters);
        const visibleGroupKeys = new Set(Object.keys(finalVisibleData));
        
        tableBody.querySelectorAll('tr[data-group-key]').forEach(row => {
            const groupKey = row.dataset.groupKey;
            const isVisible = visibleGroupKeys.has(groupKey);
            const isAccessory = row.classList.contains('accessory-row');

            if (isAccessory) {
                 const parentRow = tableBody.querySelector(`tr.bundle-row[data-group-key="${groupKey.replace(/["'|]/g, '\\$&')}"]`);
                 const isExpanded = parentRow && parentRow.classList.contains('expanded');
                 row.style.display = (isVisible && isExpanded) ? '' : 'none';
            } else {
                 row.style.display = isVisible ? '' : 'none';
            }
        });
    }

    /**
     * Inizializzazione principale
     */
    async function initialize() {
        showTableMessage("Caricamento dati in corso...");
        try {
            const response = await fetch('../api/get_spostamenti.php');
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `Errore HTTP: ${response.status}`);
            }
            groupedData = await response.json();
            renderTable(groupedData); // Renderizza la tabella iniziale
            updateUI(); // Applica i filtri (inizialmente vuoti) e popola le select
        } catch (error) {
            console.error("Errore nel caricamento dei dati:", error);
            showTableMessage(`Impossibile caricare i dati: ${error.message}`, true);
        }
    }

    // Aggiunta degli event listener
    allSelects.forEach(select => select.addEventListener('change', updateUI));
    searchInput.addEventListener('input', updateUI);
    
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allSelects.forEach(select => select.value = '');
        tableBody.querySelectorAll('.bundle-row.expanded').forEach(row => row.classList.remove('expanded'));
        updateUI();
    });

    headers.forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            // Logica di ordinamento (invariata)
            const columnIndex = Array.from(headerCell.parentNode.children).indexOf(headerCell);
            const currentDirection = headerCell.classList.contains('sort-asc') ? 'asc' : (headerCell.classList.contains('sort-desc') ? 'desc' : null);
            const isDateColumn = headerCell.dataset.type === 'date';
            const newDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
            
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            headerCell.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');
            
            const rowsToSort = Array.from(tableBody.querySelectorAll('tr:not(.accessory-row)'));
            
            rowsToSort.sort((a, b) => {
                const aVal = a.children[columnIndex]?.innerText.trim() || '';
                const bVal = b.children[columnIndex]?.innerText.trim() || '';
                if (isDateColumn) {
                    const dateA = aVal && aVal !== '-' ? new Date(aVal.split('/').reverse().join('-')) : new Date(0);
                    const dateB = bVal && bVal !== '-' ? new Date(bVal.split('/').reverse().join('-')) : new Date(0);
                    return newDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }
                const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return newDirection === 'asc' ? comparison : -comparison;
            });
            
            rowsToSort.forEach(row => {
                tableBody.appendChild(row);
                const groupKey = row.dataset.groupKey;
                if (groupKey) {
                    const accessoryRows = tableBody.querySelectorAll(`.accessory-row[data-group-key="${groupKey.replace(/["'|]/g, '\\$&')}"]`);
                    accessoryRows.forEach(accRow => tableBody.appendChild(accRow));
                }
            });
        });
    });

    tableBody.addEventListener('click', function(e) {
        const bundleRow = e.target.closest('.bundle-row');
        if (bundleRow && !e.target.closest('.action-buttons')) {
            bundleRow.classList.toggle('expanded');
            updateUI();
        }
    });

    // Avvia l'applicazione
    initialize();
});
