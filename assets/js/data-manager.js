/**
 * BONUS TS 825 Hesap Programı
 * JavaScript Veri Yöneticisi (PHP Olmadan)
 */

class DataManager {
    constructor() {
        this.storageKey = 'ts825_data';
        this.initializeData();
    }

    initializeData() {
        if (!localStorage.getItem(this.storageKey)) {
            const initialData = {
                projects: [],
                calculations: [],
                materials: this.getDefaultMaterials(),
                stats: {
                    total: 0,
                    completed: 0,
                    in_progress: 0,
                    draft: 0
                }
            };
            this.saveData(initialData);
        }
    }

    getData() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : this.getDefaultData();
        } catch (error) {
            console.error('Veri okuma hatası:', error);
            return this.getDefaultData();
        }
    }

    saveData(data) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('Veri kaydetme hatası:', error);
            return false;
        }
    }

    getDefaultData() {
        return {
            projects: [],
            calculations: [],
            materials: this.getDefaultMaterials(),
            stats: { total: 0, completed: 0, in_progress: 0, draft: 0 }
        };
    }

    getDefaultMaterials() {
        return {
            'concrete': { name: 'Beton', conductivity: 1.75, density: 2400, specific_heat: 1000 },
            'brick': { name: 'Tuğla', conductivity: 0.70, density: 1800, specific_heat: 1000 },
            'insulation_eps': { name: 'EPS Yalıtım', conductivity: 0.035, density: 20, specific_heat: 1450 },
            'insulation_xps': { name: 'XPS Yalıtım', conductivity: 0.030, density: 35, specific_heat: 1450 },
            'insulation_mw': { name: 'Mineral Yün', conductivity: 0.040, density: 100, specific_heat: 1030 },
            'plaster': { name: 'Sıva', conductivity: 0.87, density: 1800, specific_heat: 1000 },
            'wood': { name: 'Ahşap', conductivity: 0.13, density: 500, specific_heat: 1600 },
            'steel': { name: 'Çelik', conductivity: 50.0, density: 7850, specific_heat: 460 },
            'glass': { name: 'Cam', conductivity: 1.0, density: 2500, specific_heat: 750 }
        };
    }

    // Proje işlemleri
    createProject(projectData) {
        const data = this.getData();
        const newProject = {
            id: Date.now(),
            name: projectData.name,
            description: projectData.description || '',
            building_type: projectData.building_type,
            climate_zone: parseInt(projectData.climate_zone),
            total_area: parseFloat(projectData.total_area) || 0,
            status: 'draft',
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
        };

        data.projects.push(newProject);
        this.updateStats(data);
        this.saveData(data);

        return {
            success: true,
            message: 'Proje başarıyla oluşturuldu',
            project_id: newProject.id,
            data: newProject
        };
    }

    getProjects(options = {}) {
        const data = this.getData();
        let projects = [...data.projects];

        // Filtreleme
        if (options.search) {
            const search = options.search.toLowerCase();
            projects = projects.filter(p => 
                p.name.toLowerCase().includes(search) || 
                (p.description && p.description.toLowerCase().includes(search))
            );
        }

        if (options.status) {
            projects = projects.filter(p => p.status === options.status);
        }

        if (options.building_type) {
            projects = projects.filter(p => p.building_type === options.building_type);
        }

        // Sıralama
        projects.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at));

        // Sayfalama
        const page = parseInt(options.page) || 1;
        const limit = parseInt(options.limit) || 10;
        const offset = (page - 1) * limit;
        const total = projects.length;
        const pagedProjects = projects.slice(offset, offset + limit);

        // Formatla
        const formattedProjects = pagedProjects.map(project => ({
            ...project,
            building_type_name: this.getBuildingTypeName(project.building_type),
            climate_zone_name: this.getClimateZoneName(project.climate_zone),
            created_at_formatted: this.formatDate(project.created_at),
            updated_at_formatted: this.formatDate(project.updated_at)
        }));

        return {
            success: true,
            data: formattedProjects,
            pagination: {
                page: page,
                limit: limit,
                total: total,
                pages: Math.ceil(total / limit)
            }
        };
    }

    getProject(id) {
        const data = this.getData();
        const project = data.projects.find(p => p.id == id);

        if (!project) {
            return { success: false, error: 'Proje bulunamadı' };
        }

        const formattedProject = {
            ...project,
            building_type_name: this.getBuildingTypeName(project.building_type),
            climate_zone_name: this.getClimateZoneName(project.climate_zone),
            calculations: data.calculations.filter(c => c.project_id == id),
            building_elements: []
        };

        return { success: true, data: formattedProject };
    }

    deleteProject(id) {
        const data = this.getData();
        const projectIndex = data.projects.findIndex(p => p.id == id);

        if (projectIndex === -1) {
            return { success: false, error: 'Proje bulunamadı' };
        }

        data.projects.splice(projectIndex, 1);
        // İlgili hesaplamaları da sil
        data.calculations = data.calculations.filter(c => c.project_id != id);
        
        this.updateStats(data);
        this.saveData(data);

        return { success: true, message: 'Proje başarıyla silindi' };
    }

    getStats() {
        const data = this.getData();
        this.updateStats(data);
        this.saveData(data);

        return {
            success: true,
            data: {
                projects: data.stats,
                calculations: data.calculations.length
            }
        };
    }

    updateStats(data) {
        const stats = {
            total: data.projects.length,
            completed: 0,
            in_progress: 0,
            draft: 0
        };

        data.projects.forEach(project => {
            if (stats.hasOwnProperty(project.status)) {
                stats[project.status]++;
            }
        });

        data.stats = stats;
    }

    // Hesaplama işlemleri
    calculateThermalTransmittance(inputData) {
        const { layers, element_type = 'wall', climate_zone = 3 } = inputData;

        if (!layers || !Array.isArray(layers)) {
            return { success: false, error: 'Geçersiz katman verisi' };
        }

        // Yüzey dirençleri
        const rsi = 0.13; // İç yüzey direnci (m²K/W)
        const rse = 0.04; // Dış yüzey direnci (m²K/W)
        
        let totalResistance = rsi + rse;
        const layerDetails = [];

        // Katman dirençlerini hesapla
        for (const layer of layers) {
            const thickness = parseFloat(layer.thickness) / 1000; // mm'den m'ye
            const conductivity = parseFloat(layer.conductivity);
            
            if (conductivity <= 0) {
                return { success: false, error: 'Geçersiz ısı iletkenlik değeri' };
            }

            const resistance = thickness / conductivity;
            totalResistance += resistance;
            
            layerDetails.push({
                name: layer.name,
                thickness: layer.thickness,
                conductivity: conductivity,
                resistance: Math.round(resistance * 10000) / 10000
            });
        }

        // U değeri hesaplama
        const uValue = 1 / totalResistance;

        // TS 825 limit değerleri
        const limitValue = this.getThermalLimitValue(element_type, climate_zone);
        const compliant = uValue <= limitValue;

        const result = {
            u_value: Math.round(uValue * 10000) / 10000,
            total_resistance: Math.round(totalResistance * 10000) / 10000,
            limit_value: limitValue,
            compliant: compliant,
            layer_details: layerDetails,
            surface_resistances: { rsi, rse },
            element_type: element_type,
            climate_zone: climate_zone
        };

        return { success: true, data: result };
    }

    saveCalculation(calculationData) {
        const data = this.getData();
        const newCalculation = {
            id: Date.now(),
            project_id: calculationData.project_id,
            calculation_type: calculationData.calculation_type,
            input_data: calculationData.input_data,
            result_data: calculationData.result_data,
            created_at: new Date().toISOString()
        };

        data.calculations.push(newCalculation);
        this.saveData(data);

        return {
            success: true,
            message: 'Hesaplama başarıyla kaydedildi',
            calculation_id: newCalculation.id
        };
    }

    // Demo veriler
    createDemoData() {
        const demoData = {
            projects: [
                {
                    id: 1001,
                    name: 'Konut Projesi A',
                    description: 'Modern konut projesi - 3 katlı villa',
                    building_type: 'residential',
                    climate_zone: 3,
                    total_area: 250.50,
                    status: 'completed',
                    created_at: '2024-05-20T10:30:00.000Z',
                    updated_at: '2024-05-28T15:45:00.000Z'
                },
                {
                    id: 1002,
                    name: 'Ofis Binası B',
                    description: 'Ticari ofis binası - 5 katlı',
                    building_type: 'office',
                    climate_zone: 2,
                    total_area: 1200.00,
                    status: 'in_progress',
                    created_at: '2024-05-22T14:20:00.000Z',
                    updated_at: '2024-05-27T09:15:00.000Z'
                },
                {
                    id: 1003,
                    name: 'Okul Binası C',
                    description: 'İlkokul binası projesi',
                    building_type: 'educational',
                    climate_zone: 4,
                    total_area: 800.75,
                    status: 'draft',
                    created_at: '2024-05-25T11:10:00.000Z',
                    updated_at: '2024-05-26T16:30:00.000Z'
                }
            ],
            calculations: [],
            materials: this.getDefaultMaterials(),
            stats: { total: 3, completed: 1, in_progress: 1, draft: 1 }
        };

        this.saveData(demoData);

        return {
            success: true,
            message: 'Demo veriler başarıyla oluşturuldu',
            data: {
                projects: demoData.projects.length,
                calculations: demoData.calculations.length
            }
        };
    }

    // Yardımcı fonksiyonlar
    getBuildingTypeName(type) {
        const types = {
            'residential': 'Konut',
            'office': 'Ofis',
            'commercial': 'Ticari',
            'educational': 'Eğitim',
            'healthcare': 'Sağlık',
            'industrial': 'Endüstriyel',
            'other': 'Diğer'
        };
        return types[type] || 'Bilinmiyor';
    }

    getClimateZoneName(zone) {
        const zones = {
            1: '1. Bölge (En Soğuk)',
            2: '2. Bölge (Çok Soğuk)',
            3: '3. Bölge (Soğuk)',
            4: '4. Bölge (Ilık)',
            5: '5. Bölge (Sıcak)',
            6: '6. Bölge (En Sıcak)'
        };
        return zones[zone] || 'Bilinmiyor';
    }

    getThermalLimitValue(elementType, climateZone) {
        const limits = {
            'wall': { 1: 0.57, 2: 0.48, 3: 0.40, 4: 0.34, 5: 0.29, 6: 0.25 },
            'roof': { 1: 0.30, 2: 0.25, 3: 0.20, 4: 0.18, 5: 0.15, 6: 0.13 },
            'floor': { 1: 0.58, 2: 0.48, 3: 0.40, 4: 0.34, 5: 0.29, 6: 0.25 },
            'window': { 1: 2.40, 2: 2.00, 3: 1.80, 4: 1.60, 5: 1.40, 6: 1.20 }
        };
        return limits[elementType]?.[climateZone] || 1.0;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    }
}

// Global instance
window.dataManager = new DataManager();
