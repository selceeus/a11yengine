import { useCallback, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area } from 'react-easy-crop';
import { router, usePage } from '@inertiajs/react';
import { Camera, Trash2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { getCroppedImg } from '@/lib/crop-image';
import AvatarController from '@/actions/App/Http/Controllers/Settings/AvatarController';

export default function AvatarUploader() {
    const { auth } = usePage().props;
    const user = auth.user;
    const getInitials = useInitials();

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [rawSrc, setRawSrc] = useState<string | null>(null);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);
    const [uploading, setUploading] = useState(false);

    const onCropComplete = useCallback((_: Area, areaPixels: Area) => {
        setCroppedAreaPixels(areaPixels);
    }, []);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        const objectUrl = URL.createObjectURL(file);
        setRawSrc(objectUrl);
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        // Reset input so the same file can be re-selected
        e.target.value = '';
    }

    async function handleApply() {
        if (!rawSrc || !croppedAreaPixels) return;
        setUploading(true);

        try {
            const blob = await getCroppedImg(rawSrc, croppedAreaPixels);
            const file = new File([blob], 'avatar.png', { type: 'image/png' });

            const formData = new FormData();
            formData.append('avatar', file);

            router.post(AvatarController.store().url, formData, {
                forceFormData: true,
                onFinish: () => {
                    setUploading(false);
                    setRawSrc(null);
                    URL.revokeObjectURL(rawSrc);
                },
            });
        } catch {
            setUploading(false);
        }
    }

    function handleCancel() {
        if (rawSrc) URL.revokeObjectURL(rawSrc);
        setRawSrc(null);
    }

    function handleRemove() {
        router.delete(AvatarController.destroy().url);
    }

    return (
        <div className="flex items-center gap-4">
            <div className="relative">
                <Avatar className="h-16 w-16">
                    <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                    <AvatarFallback className="bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm transition-opacity hover:opacity-90"
                    aria-label="Upload profile photo"
                >
                    <Camera className="h-3 w-3" />
                </button>
            </div>

            <div className="flex flex-col gap-1">
                <p className="text-sm font-medium">Profile photo</p>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                        Upload photo
                    </Button>
                    {user.avatar && (
                        <Button type="button" variant="ghost" size="sm" onClick={handleRemove} className="text-destructive hover:text-destructive">
                            <Trash2 className="mr-1 h-3 w-3" />
                            Remove
                        </Button>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">JPG, PNG, GIF or WebP. Max 2 MB.</p>
            </div>

            <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                className="hidden"
                onChange={handleFileChange}
            />

            <Dialog open={!!rawSrc} onOpenChange={(open) => { if (!open) handleCancel(); }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Crop your photo</DialogTitle>
                    </DialogHeader>

                    <div className="relative h-72 w-full overflow-hidden rounded bg-neutral-900">
                        {rawSrc && (
                            <Cropper
                                image={rawSrc}
                                crop={crop}
                                zoom={zoom}
                                aspect={1}
                                cropShape="round"
                                showGrid={false}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={onCropComplete}
                            />
                        )}
                    </div>

                    <div className="flex items-center gap-3 px-1">
                        <span className="text-xs text-muted-foreground">Zoom</span>
                        <input
                            type="range"
                            min={1}
                            max={3}
                            step={0.05}
                            value={zoom}
                            onChange={(e) => setZoom(Number(e.target.value))}
                            className="flex-1 accent-primary"
                            aria-label="Zoom"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleCancel} disabled={uploading}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={handleApply} disabled={uploading}>
                            {uploading ? 'Uploading…' : 'Apply & Save'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
