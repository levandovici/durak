using System.Collections.Generic;

public class Player
{
    public int Id { get; set; }
    public string Name { get; set; }
    public List<Card> Hand { get; set; }
    public bool IsTurn { get; set; }

    public Player(int id, string name)
    {
        Id = id;
        Name = name;
        Hand = new List<Card>();
        IsTurn = false;
    }

    public void AddCard(Card card)
    {
        Hand.Add(card);
    }
}