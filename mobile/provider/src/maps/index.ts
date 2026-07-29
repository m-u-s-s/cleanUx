/**
 * La garde de carte et le repli OpenStreetMap sont partagés avec l'application cliente : ils
 * vivent dans mobile/shared/src/ui. Ce fichier n'existe plus que pour préserver les imports
 * `@/maps` déjà en place dans cette application.
 */
export { loadMapModule, isMapRenderable } from '@/ui/mapModule';
export { OsmMap } from '@/ui/OsmMap';
export type { OsmMarker } from '@/ui/OsmMap';
export type { MapModule } from '@/ui/mapModule';
