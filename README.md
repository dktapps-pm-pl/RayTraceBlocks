# RayTraceBlocks
PocketMine-MP plugin demonstrating a fast block ray-tracing algorithm

## Usage
When the player right-clicks (or click-hold on mobile) the plugin will perform a ray-trace along the line the player is looking along. 
It will change the first block it hits to glass, with a nice explosion sound and particle.
The range is limited to 50 blocks - anything outside of a 50-block radius will report "out of bounds".

## Caveats
The vanilla Minecraft Bedrock client only sends rotation changes above a certain threshold. This means that in some cases the server may
target a different block (slightly off-target) than you're aiming at under your crosshair, because the server does not know that you've moved.

## Future usages
This plugin is a test for the demonstrated algorithm. Once the algorithm has been fine-tuned, it may be used in PocketMine-MP for projectiles.
