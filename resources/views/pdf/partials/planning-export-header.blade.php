<div class="doc-header">
    <div class="doc-header-main">
        <h1>{{ $title }}</h1>
        <p class="doc-subtitle">{{ $subtitle }}</p>
    </div>
    <div class="doc-header-meta">
        <p class="doc-meta-line doc-meta-strong">Exercice {{ $year }}</p>
        <p class="doc-meta-line">{{ $vehicleCount }} véhicule{{ $vehicleCount > 1 ? 's' : '' }}</p>
        @if ($companyName !== null)
            <p class="doc-meta-line">Entreprise : {{ $companyName }}</p>
        @endif
        <p class="doc-meta-line doc-generated">Généré le {{ $generatedAtLabel }}</p>
    </div>
</div>
