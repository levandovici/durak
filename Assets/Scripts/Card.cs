using UnityEngine;

public class Card : MonoBehaviour
{
    public string Suit { get; set; }
    public string Rank { get; set; }
    public SpriteRenderer spriteRenderer;

    public void SetCard(string suit, string rank, Sprite sprite)
    {
        Suit = suit;
        Rank = rank;
        spriteRenderer.sprite = sprite;
    }
}