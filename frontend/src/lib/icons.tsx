import type { ReactNode } from 'react'
import {
  Car, Waves, Flame, Zap, Building2, Bandage, Users, Mountain, Activity, Trees, Tornado, Snowflake, TriangleAlert,
  Hospital, Ambulance, Truck, Shield, LifeBuoy, HeartHandshake, Hand, MapPin, MessageCircle, CircleCheck, Bell, User,
} from 'lucide-react'

const s = { size: 18, strokeWidth: 2 }
/** One consistent icon set (lucide) for categories, organizations and notifications. No emoji in UI. */
export const CAT_ICON: Record<string, ReactNode> = {
  road_accident: <Car {...s} />, flood: <Waves {...s} />, fire: <Flame {...s} />, electrical: <Zap {...s} />, building_collapse: <Building2 {...s} />,
  injury: <Bandage {...s} />, crowd_stampede: <Users {...s} />, landslide: <Mountain {...s} />, earthquake: <Activity {...s} />, wildfire: <Trees {...s} />,
  tsunami: <Waves {...s} />, cyclone_storm: <Tornado {...s} />, snow_ice: <Snowflake {...s} />, other: <TriangleAlert {...s} />,
}
export const ORG_ICON: Record<string, ReactNode> = {
  hospital: <Hospital {...s} />, ambulance: <Ambulance {...s} />, fire_service: <Truck {...s} />, police: <Shield {...s} />,
  help_center: <LifeBuoy {...s} />, ngo: <HeartHandshake {...s} />, volunteer: <Hand {...s} />, citizen: <User {...s} />,
}
export const PIN = <MapPin {...s} />
export const notifIcon = (type: string) => type === 'nearby' ? <MapPin {...s} /> : type === 'comment' ? <MessageCircle {...s} /> : type === 'status' ? <CircleCheck {...s} /> : <Bell {...s} />
