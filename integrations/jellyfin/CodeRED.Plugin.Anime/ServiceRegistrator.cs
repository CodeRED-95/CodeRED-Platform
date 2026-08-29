using CodeRED.Plugin.Anime.Api;
using CodeRED.Plugin.Anime.Channels;
using MediaBrowser.Controller;
using MediaBrowser.Controller.Channels;
using MediaBrowser.Controller.Plugins;
using Microsoft.Extensions.DependencyInjection;

namespace CodeRED.Plugin.Anime;

public sealed class ServiceRegistrator : IPluginServiceRegistrator
{
    public void RegisterServices(IServiceCollection serviceCollection, IServerApplicationHost applicationHost)
    {
        serviceCollection.AddHttpClient<CodeRedAnimeClient>();
        serviceCollection.AddSingleton<IChannel, CodeRedAnimeChannel>();
    }
}
